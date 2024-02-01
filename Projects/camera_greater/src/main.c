#include <stdio.h>

#include "esp_camera.h"
#include "driver/gpio.h"
#include "driver/spi_master.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"

#define max_length 65528

void app_main() {
    camera_config_t camera_config = { 0 };
    spi_bus_config_t buscfg = { 0 };
    spi_device_interface_config_t devcfg = { 0 };
    spi_device_handle_t spi;
    camera_fb_t *pic;
    spi_transaction_t trans = { 0 };

    gpio_reset_pin(GPIO_NUM_33);
    gpio_set_direction(GPIO_NUM_33, GPIO_MODE_OUTPUT);

    camera_config.pin_pwdn = 32;
    camera_config.pin_reset = -1;
    camera_config.pin_xclk = 0;
    camera_config.pin_sccb_sda = 26;
    camera_config.pin_sccb_scl = 27;
    camera_config.pin_d7 = 35;
    camera_config.pin_d6 = 34;
    camera_config.pin_d5 = 39;
    camera_config.pin_d4 = 36;
    camera_config.pin_d3 = 21;
    camera_config.pin_d2 = 19;
    camera_config.pin_d1 = 18;
    camera_config.pin_d0 = 5;
    camera_config.pin_vsync = 25;
    camera_config.pin_href = 23;
    camera_config.pin_pclk = 22;
    camera_config.xclk_freq_hz = 20000000;
    camera_config.ledc_timer = LEDC_TIMER_0;
    camera_config.ledc_channel = LEDC_CHANNEL_0;
    camera_config.pixel_format = PIXFORMAT_JPEG;
    camera_config.frame_size = FRAMESIZE_XGA;
    camera_config.jpeg_quality = 15;
    camera_config.fb_count = 1;
    camera_config.fb_location = CAMERA_FB_IN_DRAM;
    camera_config.grab_mode = CAMERA_GRAB_WHEN_EMPTY;
    ESP_ERROR_CHECK(esp_camera_init(&camera_config));

    buscfg.miso_io_num = 12;
    buscfg.mosi_io_num = 13;
    buscfg.sclk_io_num = 14;
    buscfg.quadwp_io_num = -1;
    buscfg.quadhd_io_num = -1;
    buscfg.max_transfer_sz = max_length;
    ESP_ERROR_CHECK(spi_bus_initialize(HSPI_HOST, &buscfg, 2));

    devcfg.clock_speed_hz = 10000000;
    devcfg.spics_io_num = 15;
    devcfg.cs_ena_posttrans = 3;
    devcfg.queue_size = 1;
    ESP_ERROR_CHECK(spi_bus_add_device(HSPI_HOST, &devcfg, &spi));

    while (1) {
        gpio_set_level(GPIO_NUM_33, 0);
        vTaskDelay(500 / portTICK_PERIOD_MS);
        gpio_set_level(GPIO_NUM_33, 1);
        pic = esp_camera_fb_get();
        trans.tx_buffer = pic->buf;
        trans.length = pic->len << 3;
        spi_device_transmit(spi, &trans);
        esp_camera_fb_return(pic);
        vTaskDelay(20000 / portTICK_PERIOD_MS);
    }
}
