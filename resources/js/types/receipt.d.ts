export type ThermalReceiptLine = {
    name: string;
    quantity_label: string;
    unit_price: string;
    line_total: string;
    lot: string | null;
};

export type ThermalReceiptPayment = {
    label: string;
    amount: string;
};

export type ThermalReceiptLegalLine = {
    label: string;
    value: string;
};

export type ThermalReceiptPayload = {
    sale_id: string;
    sale_number: string;
    brand_name: string;
    pharmacy_name: string;
    site_name: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    legal_lines: ThermalReceiptLegalLine[];
    logo_src: string | null;
    sold_at_date: string;
    sold_at_time: string;
    cashier_name: string;
    customer_name: string;
    payment_label: string;
    register_number: string | null;
    transaction_ref: string | null;
    lines: ThermalReceiptLine[];
    subtotal: string;
    discount: string | null;
    tax: string | null;
    grand_total: string;
    amount_paid: string;
    change: string | null;
    item_count: number;
    item_count_label: string;
    note: string | null;
    return_policy: string | null;
    footer_message: string;
    qr_svg: string | null;
    show_qr: boolean;
    currency_symbol: string;
    is_reprint: boolean;
    status_label: string | null;
};
