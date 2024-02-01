#include <stdio.h>

#include "host/ble_gap.h"
#include "host/ble_l2cap.h"
#include "host/util/util.h"
#include "nimble/nimble_port.h"
#include "nvs_flash.h"

#define max_length 4092

uint16_t conn_handle;

struct ble_l2cap_chan *chan;

struct os_mempool sdu_mbuf_mempool;

struct os_mbuf_pool sdu_os_mbuf_pool;

os_membuf_t sdu_mem[OS_MEMPOOL_SIZE(3, max_length)];

int l2cap_event_cb(struct ble_l2cap_event *event, void *arg) {
    struct ble_l2cap_chan_info chan_info;

    switch(event->type) {
    case BLE_L2CAP_EVENT_COC_CONNECTED:
        ESP_ERROR_CHECK(ble_l2cap_get_chan_info(event->connect.chan, &chan_info));
        printf("l2cap connected psm scid dcid=%d %d %d\n", chan_info.psm, chan_info.scid,
                chan_info.dcid);
        break;
    case BLE_L2CAP_EVENT_COC_DISCONNECTED:
        printf("l2cap disconnected\n");
        break;
    case BLE_L2CAP_EVENT_COC_DATA_RECEIVED:
        printf("l2cap received %d bytes\n", event->receive.sdu_rx->om_len);
        break;
    case BLE_L2CAP_EVENT_COC_ACCEPT:
        chan = event->accept.chan;
        ESP_ERROR_CHECK(ble_l2cap_get_chan_info(chan, &chan_info));
        printf("l2cap accepted psm scid dcid=%d %d %d\n", chan_info.psm, chan_info.scid,
                chan_info.dcid);
        ESP_ERROR_CHECK(ble_l2cap_recv_ready(chan, os_mbuf_get_pkthdr(&sdu_os_mbuf_pool, 0)));
        break;
    default:
        printf("l2cap event type=%d\n", event->type);
    }
    return 0;
}

int gap_event(struct ble_gap_event *event, void *arg) {
    switch (event->type) {
    case BLE_GAP_EVENT_CONNECT:
        conn_handle = event->connect.conn_handle;
        printf("gap connected conn_handle=%d\n", conn_handle);
        ESP_ERROR_CHECK(ble_l2cap_create_server(0x1002, max_length, l2cap_event_cb, NULL));
        break;
    case BLE_GAP_EVENT_DISCONNECT:
        ESP_ERROR_CHECK(ble_gap_ext_adv_start(1, 0, 0));
        printf("gap disconnected\n");
        break;
    case BLE_GAP_EVENT_ADV_COMPLETE:
        ESP_ERROR_CHECK(ble_gap_ext_adv_start(1, 0, 0));
        printf("gap adv complete\n");
        break;
    default:
        printf("gap event type=%d\n", event->type);
    }
    return 0;
}

void on_sync() {
    uint8_t addr_val[6];
    int i = -1;
    struct ble_gap_ext_adv_params params = { 0 };

    ESP_ERROR_CHECK(ble_hs_util_ensure_addr(0));
    ESP_ERROR_CHECK(ble_hs_id_copy_addr(BLE_ADDR_PUBLIC, addr_val, NULL));
    printf("address");
    while (++i < 6) {
        printf(" %d", addr_val[i]);
    }
    printf("\n");
    params.connectable = 1;
    params.own_addr_type = BLE_ADDR_PUBLIC;
    params.primary_phy = BLE_GAP_LE_PHY_1M;
    params.secondary_phy = BLE_GAP_LE_PHY_2M;
    params.sid = 1;
    ESP_ERROR_CHECK(ble_gap_ext_adv_configure(1, &params, NULL, gap_event, NULL));
    ESP_ERROR_CHECK(ble_gap_ext_adv_start(1, 0, 0));
}

void app_main() {
    ESP_ERROR_CHECK(nvs_flash_init());
    nimble_port_init();
    ESP_ERROR_CHECK(os_mempool_init(&sdu_mbuf_mempool, 3, max_length, sdu_mem, "sdu_pool"));
    ESP_ERROR_CHECK(os_mbuf_pool_init(&sdu_os_mbuf_pool, &sdu_mbuf_mempool, max_length, 3));
    ble_hs_cfg.sync_cb = on_sync;
    nimble_port_run();
}