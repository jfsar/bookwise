/**
 * Morph types that may receive attachments (mirrors Attachment::attachableTypes()
 * in app/Models/Attachment.php, SRS §7.38). The backend rejects anything outside
 * this allow-list, so these strings must match the Eloquent class names exactly.
 */
export const ATTACHABLE_TYPES = {
    journalEntry: 'App\\Models\\JournalEntry',
    invoice: 'App\\Models\\CustomerInvoice',
    bill: 'App\\Models\\VendorBill',
    customerPayment: 'App\\Models\\CustomerPayment',
    vendorPayment: 'App\\Models\\VendorPayment',
    bankTransaction: 'App\\Models\\BankTransaction',
};
