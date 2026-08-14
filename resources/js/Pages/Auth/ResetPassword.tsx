import GuestLayout from '@/Components/GuestLayout';
import { LockOutlined } from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import { Button, Form, Input } from 'antd';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = () => post(route('password.store'));

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <Form layout="vertical" onFinish={submit}>
                <Form.Item label="Email">
                    <Input value={data.email} disabled />
                </Form.Item>

                <Form.Item
                    label="Password Baru"
                    validateStatus={errors.password ? 'error' : undefined}
                    help={errors.password}
                >
                    <Input.Password
                        prefix={<LockOutlined />}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    label="Konfirmasi Password"
                    validateStatus={errors.password_confirmation ? 'error' : undefined}
                    help={errors.password_confirmation}
                >
                    <Input.Password
                        prefix={<LockOutlined />}
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        autoComplete="new-password"
                    />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Reset Password
                    </Button>
                </Form.Item>
            </Form>
        </GuestLayout>
    );
}
