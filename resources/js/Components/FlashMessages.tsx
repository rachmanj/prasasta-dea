import { usePage } from '@inertiajs/react';
import { App } from 'antd';
import { useEffect, useRef } from 'react';

export function FlashMessages() {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const { message } = App.useApp();
    const last = useRef<{ s?: string; e?: string }>({});

    useEffect(() => {
        if (flash?.success && flash.success !== last.current.s) {
            last.current.s = flash.success;
            message.success(flash.success);
        }
        if (flash?.error && flash.error !== last.current.e) {
            last.current.e = flash.error;
            message.error(flash.error);
        }
    }, [flash, message]);

    return null;
}
