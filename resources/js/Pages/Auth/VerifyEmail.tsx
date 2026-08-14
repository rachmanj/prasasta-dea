import GuestLayout from '@/Components/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Typography } from 'antd';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit = () => post(route('verification.send'));

    return (
        <GuestLayout>
            <Head title="Verifikasi Email" />

            <Typography.Paragraph>
                Terima kasih sudah mendaftar! Cek email kamu untuk link verifikasi.
            </Typography.Paragraph>

            {status === 'verification-link-sent' && (
                <Typography.Paragraph type="success">
                    Link verifikasi baru sudah dikirim ke email kamu.
                </Typography.Paragraph>
            )}

            <Button
                type="primary"
                block
                loading={processing}
                onClick={submit}
                style={{ marginBottom: 12 }}
            >
                Kirim Ulang Link
            </Button>

            <div style={{ textAlign: 'center' }}>
                <Link href={route('logout')} method="post" as="button">
                    Keluar
                </Link>
            </div>
        </GuestLayout>
    );
}
