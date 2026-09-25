// SPDX-License-Identifier: MIT
// Translate per-device block request events into front-panel LED pulses.
#define _GNU_SOURCE
#include <ctype.h>
#include <dirent.h>
#include <errno.h>
#include <fcntl.h>
#include <limits.h>
#include <poll.h>
#include <signal.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <time.h>
#include <unistd.h>

#define BAY_COUNT 4
#define TRACE_DIR "/sys/kernel/debug/tracing/instances/ugreen-dxp4800gt-leds"
#define SHOT_INTERVAL_NS 70000000ULL

struct bay {
    int port;
    unsigned int major;
    unsigned int minor;
    int present;
    uint64_t last_shot_ns;
};

static struct bay bays[BAY_COUNT];
static volatile sig_atomic_t stopping;

static void stop_signal(int signo)
{
    (void)signo;
    stopping = 1;
}

static uint64_t monotonic_ns(void)
{
    struct timespec now;
    if (clock_gettime(CLOCK_MONOTONIC, &now) != 0)
        return 0;
    return (uint64_t)now.tv_sec * 1000000000ULL + now.tv_nsec;
}

static int write_control(const char *path, const char *value)
{
    int fd = open(path, O_WRONLY | O_CLOEXEC);
    if (fd < 0)
        return -1;
    size_t length = strlen(value);
    ssize_t written = write(fd, value, length);
    int result = written == (ssize_t)length ? 0 : -1;
    close(fd);
    return result;
}

static int disk_name(const char *name)
{
    if (name[0] != 's' || name[1] != 'd' || !name[2])
        return 0;
    for (const unsigned char *p = (const unsigned char *)name + 2; *p; ++p)
        if (!islower(*p))
            return 0;
    return 1;
}

static void refresh_bays(void)
{
    DIR *dir = opendir("/sys/class/block");
    if (!dir)
        return;
    for (int bay = 0; bay < BAY_COUNT; ++bay)
        bays[bay].present = 0;

    struct dirent *entry;
    while ((entry = readdir(dir)) != NULL) {
        if (!disk_name(entry->d_name))
            continue;
        char path[PATH_MAX], resolved[PATH_MAX];
        int length = snprintf(path, sizeof(path), "/sys/class/block/%s/device", entry->d_name);
        if (length < 0 || length >= (int)sizeof(path) || !realpath(path, resolved))
            continue;
        for (int bay = 0; bay < BAY_COUNT; ++bay) {
            char needle[32];
            snprintf(needle, sizeof(needle), "/ata%d/", bays[bay].port);
            if (!strstr(resolved, needle))
                continue;
            length = snprintf(path, sizeof(path), "/sys/class/block/%s/dev", entry->d_name);
            if (length < 0 || length >= (int)sizeof(path))
                break;
            FILE *device = fopen(path, "r");
            if (device) {
                unsigned int major, minor;
                if (fscanf(device, "%u:%u", &major, &minor) == 2) {
                    bays[bay].major = major;
                    bays[bay].minor = minor;
                    bays[bay].present = 1;
                }
                fclose(device);
            }
            break;
        }
    }
    closedir(dir);
}

static void handle_event(const char *line)
{
    const char *event = strstr(line, "block_rq_issue: ");
    if (!event)
        return;
    unsigned int major, minor;
    char operation[11];
    if (sscanf(event + strlen("block_rq_issue: "), "%u,%u %10s",
               &major, &minor, operation) != 3)
        return;
    if (operation[0] != 'R' && operation[0] != 'W')
        return;

    for (int bay = 0; bay < BAY_COUNT; ++bay) {
        if (!bays[bay].present || bays[bay].major != major || bays[bay].minor != minor)
            continue;
        uint64_t now = monotonic_ns();
        if (bays[bay].last_shot_ns && now - bays[bay].last_shot_ns < SHOT_INTERVAL_NS)
            return;
        char path[64];
        snprintf(path, sizeof(path), "/sys/class/leds/disk%d/shot", bay + 1);
        if (write_control(path, "1\n") == 0)
            bays[bay].last_shot_ns = now;
        return;
    }
}

static int parse_port(const char *value)
{
    char *end;
    errno = 0;
    long port = strtol(value, &end, 10);
    if (errno || *end || port < 1 || port > 255)
        return -1;
    return (int)port;
}

int main(int argc, char **argv)
{
    if (argc != BAY_COUNT + 1) {
        fprintf(stderr, "Usage: %s ata-port-1 ata-port-2 ata-port-3 ata-port-4\n", argv[0]);
        return 2;
    }
    for (int bay = 0; bay < BAY_COUNT; ++bay) {
        bays[bay].port = parse_port(argv[bay + 1]);
        if (bays[bay].port < 0)
            return 2;
    }
    struct sigaction action = {.sa_handler = stop_signal};
    sigemptyset(&action.sa_mask);
    sigaction(SIGTERM, &action, NULL);
    sigaction(SIGINT, &action, NULL);

    if (mkdir(TRACE_DIR, 0700) != 0 && errno != EEXIST) {
        perror("Creating isolated block trace instance");
        return 1;
    }
    const char *enable = TRACE_DIR "/events/block/block_rq_issue/enable";
    const char *tracing_on = TRACE_DIR "/tracing_on";
    if (write_control(enable, "1\n") != 0 || write_control(tracing_on, "1\n") != 0) {
        perror("Enabling block request events");
        write_control(enable, "0\n");
        rmdir(TRACE_DIR);
        return 1;
    }
    int trace_fd = open(TRACE_DIR "/trace_pipe", O_RDONLY | O_NONBLOCK | O_CLOEXEC);
    if (trace_fd < 0) {
        perror("Opening block request events");
        write_control(enable, "0\n");
        rmdir(TRACE_DIR);
        return 1;
    }

    refresh_bays();
    uint64_t next_scan_ns = monotonic_ns() + 5000000000ULL;
    char line[4096], chunk[8192];
    size_t line_length = 0;
    int overflow = 0;
    struct pollfd trace = {.fd = trace_fd, .events = POLLIN};
    while (!stopping) {
        int ready = poll(&trace, 1, 500);
        if (ready < 0 && errno != EINTR)
            break;
        if (ready > 0 && (trace.revents & (POLLERR | POLLHUP | POLLNVAL)))
            break;
        if (ready > 0 && (trace.revents & POLLIN)) {
            ssize_t count = read(trace_fd, chunk, sizeof(chunk));
            if (count < 0 && errno != EINTR && errno != EAGAIN)
                break;
            for (ssize_t i = 0; i < count; ++i) {
                if (chunk[i] == '\n') {
                    if (!overflow) {
                        line[line_length] = '\0';
                        handle_event(line);
                    }
                    line_length = 0;
                    overflow = 0;
                } else if (line_length + 1 < sizeof(line)) {
                    line[line_length++] = chunk[i];
                } else {
                    overflow = 1;
                }
            }
        }
        uint64_t now = monotonic_ns();
        if (now >= next_scan_ns) {
            refresh_bays();
            next_scan_ns = now + 5000000000ULL;
        }
    }
    close(trace_fd);
    write_control(enable, "0\n");
    rmdir(TRACE_DIR);
    return stopping ? 0 : 1;
}
