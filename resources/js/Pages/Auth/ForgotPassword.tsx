import GuestLayout from '@/Components/GuestLayout';
import { MailOutlined } from '@ant-design/icons';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Form, Input, Typography } from 'antd';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = () => post(route('password.email'));

    return (
        <GuestLayout>
            <Head title="Lupa Password" />

            <Typography.Paragraph type="secondary">
                Masukkan email kamu, kami akan kirim link reset password.
            </Typography.Paragraph>

            {status && (
                <Typography.Paragraph type="success">{status}</Typography.Paragraph>
            )}

            <Form layout="vertical" onFinish={submit}>
                <Form.Item
                    label="Email"
                    validateStatus={errors.email ? 'error' : undefined}
                    help={errors.email}
                >
                    <Input
                        prefix={<MailOutlined />}
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
                    />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Kirim Link Reset
                    </Button>
                </Form.Item>
            </Form>

            <div style={{ textAlign: 'center' }}>
                <Link href={route('login')}>Kembali ke login</Link>
            </div>
        </GuestLayout>
    );
}
