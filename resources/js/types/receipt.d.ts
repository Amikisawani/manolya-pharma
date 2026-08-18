export type ThermalReceiptLine = {
    name: string;
    quantity_label: string;
    unit_price: string;
    line_total: string;
    lot: string | null;
};

export type ThermalReceiptPayload = {
    sale_id: string;
    sale_number: string;
    brand_name: string;
    pharmacy_name: string;
    site_name: string | null;
    address: string | null;
    sold_at_date: string;
    sold_at_time: string;
    cashier_name: string;
    payment_label: string;
    register_number: string | null;
    lines: ThermalReceiptLine[];
    subtotal: string;
    discount: string | null;
    grand_total: string;
    amount_paid: string;
    change: string | null;
    item_count_label: string;
    footer_message: string;
    currency_symbol: string;
    is_reprint: boolean;
    status_label: string | null;
};
