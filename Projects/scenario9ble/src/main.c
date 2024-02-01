#include <stdio.h>

#include "driver/gpio.h"
#include "driver/spi_slave.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "host/ble_gap.h"
#include "host/ble_gatt.h"
#include "host/util/util.h"
#include "nimble/nimble_port.h"
#include "nimble/nimble_port_freertos.h"
#include "nvs_flash.h"

#define max_length 65528

int cts = 0;

uint16_t conn_handle;

uint16_t start_handle;

uint16_t end_handle;

uint16_t val_handle;

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

int peer_svc_disced(uint16_t conn_handle, const struct ble_gatt_error *error,
        const struct ble_gatt_svc *service, void *arg) {
    if (error->status != BLE_HS_EDONE) {
        start_handle = service->start_handle;
        end_handle = service->end_handle;
        printf("gatt svc disced start_handle end_handle=%d %d\n", start_handle, end_handle);
    } else {
        printf("gatt svc disced all\n");
        cts = 1;
    }
    return 0;
}

int peer_chr_disced(uint16_t conn_handle, const struct ble_gatt_error *error,
        const struct ble_gatt_chr *chr, void *arg) {
    if (error->status != BLE_HS_EDONE) {
        printf("gatt chr disced val_handle=%d\n", val_handle);
        val_handle = chr->val_handle;
    } else {
        printf("gatt chr disced all\n");
        cts = 1;
    }
    return 0;
}

int on_custom_write(uint16_t conn_handle, const struct ble_gatt_error *error,
        struct ble_gatt_attr *attr, void *arg) {
    printf("gatt written\n");
    cts = 1;
    return 0;
}

void app_main() {
    spi_bus_config_t buscfg = { 0 };
    spi_slave_interface_config_t slvcfg = { 0 };
    spi_slave_transaction_t trans = { 0 };
    ble_addr_t peer_addr = { BLE_ADDR_PUBLIC, { 222, 40, 247, 249, 85, 96 } };
    int64_t time;

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
    buscfg.max_transfer_sz = max_length;
    slvcfg.spics_io_num = 7;
    slvcfg.queue_size = 1;
    ESP_ERROR_CHECK(spi_slave_initialize(SPI2_HOST, &buscfg, &slvcfg, SPI_DMA_CH_AUTO));
    trans.rx_buffer = recvbuf + 8;
    trans.length = max_length << 3;

    ESP_ERROR_CHECK(nvs_flash_init());
    nimble_port_init();
    ble_hs_cfg.sync_cb = on_sync;
    nimble_port_freertos_init(ble_host_task);
    while (cts == 0)
        ;
    cts = 0;

    while (1) {
        spi_slave_transmit(SPI2_HOST, &trans, portMAX_DELAY);
        length = trans.trans_len;
        printf("received %d bits\n", length);
        length >>= 3;
        if (length >= 10000) {
            sprintf(recvbuf, "X%d\'", length);
            memmove(recvbuf, recvbuf + 1, 7);
            *recvbuf = 0;
            length += 8;
            recvbuf[length] = '\'';
            data = (uint8_t *) recvbuf;
        } else {
            data = (uint8_t *) recvbuf + 1;
            sprintf((char *) data, "X%d\'", length);
            memmove(recvbuf, data, 6);
            recvbuf[length + 8] = '\'';
            length += 7;
        }

        time = esp_timer_get_time();
        gpio_set_level(GPIO_NUM_2, 1);
        ESP_ERROR_CHECK(ble_gap_ext_connect(BLE_OWN_ADDR_PUBLIC, &peer_addr, 10000,
                BLE_GAP_LE_PHY_1M_MASK, NULL, NULL, NULL, gap_event, NULL));
        while (cts == 0)
            ;
        cts = 0;
        ESP_ERROR_CHECK(ble_gattc_disc_all_svcs(conn_handle, peer_svc_disced, NULL));
        while (cts == 0)
            ;
        cts = 0;
        ESP_ERROR_CHECK(ble_gattc_disc_all_chrs(conn_handle, start_handle, end_handle,
                peer_chr_disced, NULL));
        while (cts == 0)
            ;
        cts = 0;
        ESP_ERROR_CHECK(ble_gattc_write_flat(conn_handle, val_handle, data, length, on_custom_write,
                NULL));
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