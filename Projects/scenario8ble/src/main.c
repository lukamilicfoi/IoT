#include <stdio.h>

#include "driver/gpio.h"
#include "driver/spi_slave.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "host/ble_gap.h"
#include "host/ble_l2cap.h"
#include "host/util/util.h"
#include "nimble/nimble_port.h"
#include "nimble/nimble_port_freertos.h"
#include "nvs_flash.h"

#define max_length 4092

int cts = 0;

uint16_t conn_handle;

struct ble_l2cap_chan *chan;

struct os_mempool sdu_mbuf_mempool;

struct os_mbuf_pool sdu_os_mbuf_pool;

os_membuf_t sdu_mem[OS_MEMPOOL_SIZE(3, max_length)];

WORD_ALIGNED_ATTR char recvbuf[max_length + 8] = { 0 };

int length;

uint8_t *data;

void ble_host_task(void *param) {
    nimble_port_run();
}

void on_sync() {
    ESP_ERROR_CHECK(ble_hs_util_ensure_addr(0));
    printf("ble synced\n");
    cts = 1;
}

int gap_event(struct ble_gap_event *event, void *arg) {
    switch (event->type) {
    case BLE_GAP_EVENT_CONNECT:
        conn_handle = event->connect.conn_handle;
        printf("gap connected conn_handle=%d\n", conn_handle);
        cts = 1;
        break;
    case BLE_GAP_EVENT_DISCONNECT:
        printf("gap disconnected\n");
        cts = 1;
        break;
    default:
        printf("gap event type=%d\n", event->type);
    }
    return 0;
}
int l2cap_event_cb(struct ble_l2cap_event *event, void *arg) {
    struct ble_l2cap_chan_info chan_info;

    switch(event->type) {
    case BLE_L2CAP_EVENT_COC_CONNECTED:
        chan = event->connect.chan;
        ESP_ERROR_CHECK(ble_l2cap_get_chan_info(chan, &chan_info));
        printf("l2cap connected psm scid dcid=%d %d %d\n", chan_info.psm, chan_info.scid,
                chan_info.dcid);
        cts = 1;
        break;
    case BLE_L2CAP_EVENT_COC_DISCONNECTED:
        printf("l2cap disconnected\n");
        cts = 1;
        break;
    default:
        printf("l2cap event type=%d\n", event->type);
    }
    return 0;
}

void app_main() {
    spi_bus_config_t buscfg = { 0 };
    spi_slave_interface_config_t slvcfg = { 0 };
    spi_slave_transaction_t trans = { 0 };
    ble_addr_t peer_addr = { BLE_ADDR_PUBLIC, { 222, 40, 247, 249, 85, 96 } };
    int64_t time;
    struct os_mbuf *sdu_tx_data;

    gpio_reset_pin(GPIO_NUM_2);
    gpio_set_direction(GPIO_NUM_2, GPIO_MODE_OUTPUT);
    gpio_set_pull_mode(GPIO_NUM_5, GPIO_PULLUP_ONLY);
    gpio_set_pull_mode(GPIO_NUM_6, GPIO_PULLUP_ONLY);
    gpio_set_pull_mode(GPIO_NUM_7, GPIO_PULLUP_ONLY);
    buscfg.miso_io_num = 4;
    buscfg.mosi_io_num = 5;
    buscfg.sclk_io_num = 6;
    buscfg.quadwp_io_num = -1;
    buscfg.quadhd_io_num = -1;
    slvcfg.spics_io_num = 7;
    slvcfg.queue_size = 1;
    ESP_ERROR_CHECK(spi_slave_initialize(SPI2_HOST, &buscfg, &slvcfg, SPI_DMA_CH_AUTO));
    trans.rx_buffer = recvbuf + 8;
    trans.length = max_length << 3;

    ESP_ERROR_CHECK(nvs_flash_init());
    nimble_port_init();
    ble_hs_cfg.sync_cb = on_sync;
    nimble_port_freertos_init(ble_host_task);
    ESP_ERROR_CHECK(os_mempool_init(&sdu_mbuf_mempool, 3, max_length, sdu_mem, "sdu_pool"));
    ESP_ERROR_CHECK(os_mbuf_pool_init(&sdu_os_mbuf_pool, &sdu_mbuf_mempool, max_length, 3));
    while (cts == 0)
        ;
    cts = 0;

    while (1) {
        spi_slave_transmit(SPI2_HOST, &trans, portMAX_DELAY);
        length = trans.trans_len;
        printf("received %d bits\n", length);
        length >>= 3;
        if (length >= 1000) {
            data = (uint8_t *) recvbuf + 1;
            sprintf((char *) data, "X%d\'", length);
            memmove(recvbuf, data, 6);
            recvbuf[length + 8] = '\'';
            length += 7;
        } else {
            data = (uint8_t *) recvbuf + 2;
            sprintf((char *) data, "X%d\'", length);
            memmove(data - 1, data, 5);
            recvbuf[length + 8] = '\'';
            length += 6;
        }

        time = esp_timer_get_time();
        gpio_set_level(GPIO_NUM_2, 1);
        ESP_ERROR_CHECK(ble_gap_ext_connect(BLE_OWN_ADDR_PUBLIC, &peer_addr, 10000,
                BLE_GAP_LE_PHY_1M_MASK, NULL, NULL, NULL, gap_event, NULL));
        while (cts == 0)
            ;
        cts = 0;
        ESP_ERROR_CHECK(ble_l2cap_connect(conn_handle, 0x1002, max_length,
                os_mbuf_get_pkthdr(&sdu_os_mbuf_pool, 0), l2cap_event_cb, NULL));
        while (cts == 0)
            ;
        cts = 0;
        sdu_tx_data = os_mbuf_get_pkthdr(&sdu_os_mbuf_pool, 0);
        os_mbuf_append(sdu_tx_data, data, length);
        ESP_ERROR_CHECK(ble_l2cap_send(chan, sdu_tx_data));
        ESP_ERROR_CHECK(ble_l2cap_disconnect(chan));
        while (cts == 0)
            ;
        cts = 0;
        ESP_ERROR_CHECK(ble_gap_terminate(conn_handle, BLE_ERR_REM_USER_CONN_TERM));
        while (cts == 0)
            ;
        cts = 0;
        gpio_set_level(GPIO_NUM_2, 0);
        printf("%lld\n", esp_timer_get_time() - time);

        printf("sent %d bytes\n", length);
    }
}