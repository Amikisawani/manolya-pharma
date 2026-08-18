import { nextTick, onMounted } from 'vue';

/**
 * Imprime le ticket HTML isolé (58 mm), pas la page caisse A4.
 * window.print() sur le SPA envoyait une page A4 au PT-210 : le pilote
 * « ajuster à la page » réduisait le texte à une taille microscopique.
 */
export function useThermalPrint(printUrl: string, printOnLoad = false) {
    const printReceipt = () => {
        if (!printUrl) {
            return;
        }

        document.getElementById('thermal-print-frame')?.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'thermal-print-frame';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.src = printUrl;
        iframe.style.cssText =
            'position:fixed;right:0;bottom:0;width:58mm;height:80vh;border:0;opacity:0;pointer-events:none;';

        iframe.addEventListener('load', () => {
            window.setTimeout(() => {
                iframe.contentWindow?.focus();
                iframe.contentWindow?.print();
            }, 280);
        });

        document.body.appendChild(iframe);
    };

    onMounted(() => {
        if (!printOnLoad) {
            return;
        }

        void nextTick(() => {
            window.setTimeout(printReceipt, 400);
        });
    });

    return { printReceipt };
}
