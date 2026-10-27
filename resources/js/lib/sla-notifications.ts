import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

interface ConfirmOptions {
    title: string;
    text: string;
    icon?: 'warning' | 'question' | 'info' | 'success' | 'error';
    confirmButtonText?: string;
    cancelButtonText?: string;
}
interface AlertDialogOptions {
    title: string;
    text: string;
    icon?: 'warning' | 'question' | 'info' | 'success' | 'error';
    confirmButtonText?: string;
}

function themeColors() {
    const dark = document.documentElement.classList.contains('dark');

    return {
        background: dark ? '#18181b' : '#ffffff',
        color: dark ? '#fafafa' : '#18181b',
    };
}

export async function confirmAction(options: ConfirmOptions): Promise<boolean> {
    const theme = themeColors();

    const result = await Swal.fire({
        title: options.title,
        text: options.text,
        icon: 'warning',
        background: theme.background,
        color: theme.color,
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText ?? 'Ya, lanjutkan',
        cancelButtonText: options.cancelButtonText ?? 'Batal',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#71717a',
        reverseButtons: true,
        focusCancel: true,
    });

    return result.isConfirmed;
}

export function showSuccessToast(title: string): void {
    const theme = themeColors();

    void Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title,
        background: theme.background,
        color: theme.color,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
}

export function showErrorToast(title: string): void {
    const theme = themeColors();

    void Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title,
        background: theme.background,
        color: theme.color,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
    });
}

export function showAlertDialog(options: AlertDialogOptions): Promise<unknown> {
    const theme = themeColors();

    return Swal.fire({
        title: options.title,
        text: options.text,
        icon: options.icon ?? 'info',
        background: theme.background,
        color: theme.color,
        confirmButtonText: options.confirmButtonText ?? 'OK',
        confirmButtonColor: '#6366f1',
    });
}
