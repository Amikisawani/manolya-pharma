import { nextTick, onMounted } from 'vue';

export function useThermalPrint(printOnLoad = false) {
    const printReceipt = () => {
        window.print();
    };

    onMounted(() => {
        if (!printOnLoad) {
            return;
        }

        void nextTick(() => {
            window.setTimeout(printReceipt, 280);
        });
    });

    return { printReceipt };
}
