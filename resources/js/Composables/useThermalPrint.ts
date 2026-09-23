import { nextTick, onMounted } from 'vue';

/**
 * Imprime le ticket HTML isolé (58 mm) pour GOOJPRT / POS-58.
 *
 * Un iframe est impossible : SecurityHeaders envoie X-Frame-Options: DENY,
 * donc contentWindow.print() ne fait rien. On ouvre le document ticket
 * (geste utilisateur) ; la page ticket appelle window.print() elle-même.
 */
export function useThermalPrint(printUrl: string, printOnLoad = false) {
    const autoprintUrl = (): string => {
        if (!printUrl) {
            return '';
        }

        return printUrl.includes('?') ? `${printUrl}&autoprint=1` : `${printUrl}?autoprint=1`;
    };

    const openReceipt = (event?: MouseEvent): boolean => {
        const url = autoprintUrl();
        if (!url) {
            return false;
        }

        if (
            event &&
            (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
        ) {
            return false;
        }

        const popup = window.open(url, 'manolya-ticket-58mm');
        if (popup) {
            event?.preventDefault();
            popup.focus();

            return true;
        }

        if (!event) {
            window.location.assign(url);

            return true;
        }

        return false;
    };

    const printReceipt = (event?: MouseEvent) => {
        openReceipt(event);
    };

    onMounted(() => {
        if (!printOnLoad) {
            return;
        }

        void nextTick(() => {
            const current = new URL(window.location.href);
            current.searchParams.delete('print');
            current.searchParams.delete('reprint');
            const cleaned = current.pathname + (current.search ? current.search : '');
            window.history.replaceState({}, '', cleaned);

            openReceipt();
        });
    });

    return { printReceipt, autoprintUrl };
}
