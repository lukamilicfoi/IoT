#include "bt_hci_common.h"
#include "pn532.h"

#include <stdio.h>

#include "driver/gpio.h"
#include "esp_bt.h"
#include "esp_timer.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "nvs_flash.h"

esp_vhci_host_callback_t vhci_host_cb;

uint8_t hci_cmd_buf[128];

void controller_pkt_rcv_ready() { }

int host_rcv_pkt(uint8_t *data, uint16_t len) {
    return 0;
}

void app_main() {
    pn532_t nfc;
    esp_bt_controller_config_t btcfg = BT_CONTROLLER_INIT_CONFIG_DEFAULT();
    bd_addr_t addr;
    uint8_t uid[7], length, key[6] = { 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF }, read[16],
            data[6] = { 0 };
    int64_t time;

    gpio_reset_pin(GPIO_NUM_2);
    gpio_set_direction(GPIO_NUM_2, GPIO_MODE_OUTPUT);
    pn532_spi_init(&nfc, 14, 12, 13, 15);
    pn532_begin(&nfc);
    if (pn532_SAMConfig(&nfc) == 0) {
        esp_system_abort("PN532 error 1");
    }

    ESP_ERROR_CHECK(nvs_flash_init());
    ESP_ERROR_CHECK(esp_bt_mem_release(ESP_BT_MODE_CLASSIC_BT));
    ESP_ERROR_CHECK(esp_bt_controller_init(&btcfg));
    ESP_ERROR_CHECK(esp_bt_controller_enable(ESP_BT_MODE_BLE));
    vhci_host_cb.notify_host_send_available = controller_pkt_rcv_ready;
    vhci_host_cb.notify_host_recv = host_rcv_pkt;
    ESP_ERROR_CHECK(esp_vhci_host_register_callback(&vhci_host_cb));
    while (!esp_vhci_host_check_send_available())
        ;
    esp_vhci_host_send_packet(hci_cmd_buf,
            make_cmd_ble_set_adv_param(hci_cmd_buf, 32, 32, 3, 0, 0, addr, 1, 3));

    while (1) {
        if (pn532_readPassiveTargetID(&nfc, PN532_MIFARE_ISO14443A, uid, &length, 0) == 0) {
            esp_system_abort("PN532 error 2");
        }
        if (pn532_mifareclassic_AuthenticateBlock(&nfc, uid, length, 4, 1, key) == 0) {
            esp_system_abort("PN532 error 3");
        }
        if (pn532_mifareclassic_ReadDataBlock(&nfc, 4, read) == 0) {
            esp_system_abort("PN532 error 4");
        }
        length = snprintf((char *) data + 1, 5, "%d", ((read[0] << 8) + read[1])) + 1;

        time = esp_timer_get_time();
        gpio_set_level(GPIO_NUM_2, 1);
        while (!esp_vhci_host_check_send_available())
            ;
        esp_vhci_host_send_packet(hci_cmd_buf,
                make_cmd_ble_set_adv_data(hci_cmd_buf, length, data));
        while (!esp_vhci_host_check_send_available())
            ;
        esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_enable(hci_cmd_buf, 1));
        vTaskDelay(100 / portTICK_PERIOD_MS);
        while (!esp_vhci_host_check_send_available())
            ;
        esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_enable(hci_cmd_buf, 0));
        gpio_set_level(GPIO_NUM_2, 0);
        printf("%lld\n", esp_timer_get_time() - time);

        printf("%d %s %d\n", read[0], data + 1, length);
        vTaskDelay(1000 / portTICK_PERIOD_MS);
    }
}