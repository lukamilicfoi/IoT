#include <stdio.h>

#include "host/ble_gap.h"
#include "host/ble_gatt.h"
#include "host/util/util.h"
#include "nimble/nimble_port.h"
#include "nvs_flash.h"
#include "services/ans/ble_svc_ans.h"
#include "services/gap/ble_svc_gap.h"
#include "services/gatt/ble_svc_gatt.h"

uint16_t conn_handle;

uint16_t val_handle;

ble_uuid128_t gatt_svr_svc_uuid = BLE_UUID128_INIT(0x2d, 0x71, 0xa2, 0x59, 0xb4, 0x58, 0xc8, 0x12,
        0x99, 0x99, 0x43, 0x95, 0x12, 0x2f, 0x46, 0x59);

ble_uuid128_t gatt_svr_chr_uuid = BLE_UUID128_INIT(0x00, 0x00, 0x00, 0x00, 0x11, 0x11, 0x11, 0x11,
        0x22, 0x22, 0x22, 0x22, 0x33, 0x33, 0x33, 0x33);

struct ble_gatt_chr_def characteristics[2] = { { 0 } };

struct ble_gatt_svc_def gatt_svr_svcs[2] = { { 0 } };

int gap_event(struct ble_gap_event *event, void *arg) {
    switch (event->type) {
    case BLE_GAP_EVENT_CONNECT:
        conn_handle = event->connect.conn_handle;
        printf("gap connected conn_handle=%d\n", conn_handle);
        break;
    case BLE_GAP_EVENT_DISCONNECT:
        printf("gap disconnected\n");
        ESP_ERROR_CHECK(ble_gap_ext_adv_start(1, 0, 0));
        break;
    case BLE_GAP_EVENT_ADV_COMPLETE:
        printf("gap adv complete\n");
        ESP_ERROR_CHECK(ble_gap_ext_adv_start(1, 0, 0));
        break;
    default:
        printf("gap event type=%d\n", event->type);
    }
    return 0;
}

int gatt_svc_access(uint16_t conn_handle, uint16_t attr_handle, struct ble_gatt_access_ctxt *ctxt,
        void *arg) {
    printf("gatt received %d bytes\n", ctxt->om->om_len);
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
    characteristics->uuid = &gatt_svr_chr_uuid.u;
    characteristics->access_cb = gatt_svc_access;
    characteristics->flags = BLE_GATT_CHR_F_WRITE;
    characteristics->val_handle = &val_handle;
    gatt_svr_svcs->type = BLE_GATT_SVC_TYPE_PRIMARY;
    gatt_svr_svcs->uuid = &gatt_svr_svc_uuid.u;
    gatt_svr_svcs->characteristics = characteristics;
    ESP_ERROR_CHECK(ble_gatts_count_cfg(gatt_svr_svcs));
    ble_svc_gap_init();
    ble_svc_gatt_init();
    ble_svc_ans_init();
    ESP_ERROR_CHECK(ble_gatts_add_svcs(gatt_svr_svcs));
    ble_hs_cfg.sync_cb = on_sync;
    nimble_port_run();
}