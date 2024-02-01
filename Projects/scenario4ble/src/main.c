#include "bt_hci_common.h"

#include <stdio.h>

#include "ds18b20.h"
#include "esp_bt.h"
#include "esp_timer.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "nvs_flash.h"

/*for some reason putting this on the stack did not work in 4.4,
the same goes for previous and next scenarios*/
esp_vhci_host_callback_t vhci_host_cb;

/*for some reason putting this on the stack did not work in 4.4,
the same goes for previous and next scenarios*/
uint8_t hci_cmd_buf[128];

void controller_pkt_rcv_ready() { }

int host_rcv_pkt(uint8_t *data, uint16_t len) {
    return 0;
}

void app_main() {
    esp_bt_controller_config_t btcfg = BT_CONTROLLER_INIT_CONFIG_DEFAULT();
    bd_addr_t addr;
    owb_rmt_driver_info rmt_driver_info;
    OneWireBus *owb;
    DS18B20_Info *ds18b20_info = ds18b20_malloc();
    float temp;
    uint8_t data;
    int64_t time;

    ESP_ERROR_CHECK(gpio_reset_pin(GPIO_NUM_2));
    ESP_ERROR_CHECK(gpio_set_direction(GPIO_NUM_2, GPIO_MODE_OUTPUT));

    /*for some reason calling this before the next block did not work in 4.4,
    the same goes for replacing that block with waiting or waiting in between*/
    owb = owb_rmt_initialize(&rmt_driver_info, GPIO_NUM_4, RMT_CHANNEL_1, RMT_CHANNEL_0);
    owb_use_crc(owb, true);
    ds18b20_init_solo(ds18b20_info, owb);
    ds18b20_use_crc(ds18b20_info, true);
    ds18b20_set_resolution(ds18b20_info, DS18B20_RESOLUTION_12_BIT);

    ESP_ERROR_CHECK(nvs_flash_init());
    ESP_ERROR_CHECK(esp_bt_mem_release(ESP_BT_MODE_CLASSIC_BT));
    ESP_ERROR_CHECK(esp_bt_controller_init(&btcfg));
    ESP_ERROR_CHECK(esp_bt_controller_enable(ESP_BT_MODE_BLE));
    vhci_host_cb.notify_host_send_available = controller_pkt_rcv_ready;
    vhci_host_cb.notify_host_recv = host_rcv_pkt;
    /*for some reason not calling this did not work in 4.4,
    the same goes for previous and next scenarios*/
    ESP_ERROR_CHECK(esp_vhci_host_register_callback(&vhci_host_cb));
    while (!esp_vhci_host_check_send_available())
        ;
    esp_vhci_host_send_packet(hci_cmd_buf,
            make_cmd_ble_set_adv_param(hci_cmd_buf, 32, 32, 3, 0, 0, addr, 1, 3));

    while (1) {
        if (ds18b20_convert_and_read_temp(ds18b20_info, &temp) != DS18B20_OK) {
            esp_system_abort("DS18B20 error");
        }
        data = temp * 4;

        time = esp_timer_get_time();
        gpio_set_level(GPIO_NUM_2, 1);
        while (!esp_vhci_host_check_send_available())
            ;
        esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_data(hci_cmd_buf, 1, &data));
        while (!esp_vhci_host_check_send_available())
            ;
        esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_enable(hci_cmd_buf, 1));
        vTaskDelay(100 / portTICK_PERIOD_MS);
        while (!esp_vhci_host_check_send_available())
            ;
        esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_enable(hci_cmd_buf, 0));
        gpio_set_level(GPIO_NUM_2, 0);
        printf("%lld\n", esp_timer_get_time() - time);

        printf("%d\n", data);
        vTaskDelay(6000 / portTICK_PERIOD_MS);
    }
}