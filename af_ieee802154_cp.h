#ifndef AF_IEEE802154_CP_H
#define AF_IEEE802154_CP_H
#include <sys/types.h>
#include <sys/socket.h>
#include <stdio.h>
#include <stdint.h>
#include <unistd.h>
#include <string.h>

#define IEEE802154_ADDR_LEN 8
#define MAX_PACKET_LEN 127
#define EXTENDED 1

enum {
        IEEE802154_ADDR_NONE = 0x0,
        IEEE802154_ADDR_SHORT = 0x2,
        IEEE802154_ADDR_LONG = 0x3,
};

struct ieee802154_addr_sa {
        int addr_type;
        uint16_t pan_id;
        union {
                uint8_t hwaddr[IEEE802154_ADDR_LEN];
                uint16_t short_addr;
        };
};

struct sockaddr_ieee802154 {
        sa_family_t family;
        struct ieee802154_addr_sa addr;
};

#endif /* AF_IEEE802154_CP_H */
