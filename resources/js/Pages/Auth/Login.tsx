import GuestLayout from '@/Components/GuestLayout';
import { LockOutlined, MailOutlined } from '@ant-design/icons';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, Form, Input, Typography } from 'antd';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit = () => post(route('login'));

    return (
        <GuestLayout>
            <Head title="Login" />

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
                        autoComplete="username"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    label="Password"
                    validateStatus={errors.password ? 'error' : undefined}
                    help={errors.password}
                >
                    <Input.Password
                        prefix={<LockOutlined />}
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Form.Item>

                <Form.Item>
                    <Checkbox
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    >
                        Ingat saya
                    </Checkbox>
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Masuk
                    </Button>
                </Form.Item>
            </Form>

            {canResetPassword && (
                <div style={{ textAlign: 'center' }}>
                    <Link href={route('password.request')}>Lupa password?</Link>
                </div>
            )}
        </GuestLayout>
    );
}
