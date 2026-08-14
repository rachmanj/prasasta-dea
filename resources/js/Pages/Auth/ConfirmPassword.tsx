import GuestLayout from '@/Components/GuestLayout';
import { LockOutlined } from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import { Button, Form, Input, Typography } from 'antd';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors } = useForm({ password: '' });

    const submit = () => post(route('password.confirm'));

    return (
        <GuestLayout>
            <Head title="Konfirmasi Password" />

            <Typography.Paragraph type="secondary">
                Ini area aman. Konfirmasi password kamu untuk melanjutkan.
            </Typography.Paragraph>

            <Form layout="vertical" onFinish={submit}>
                <Form.Item
                    label="Password"
                    validateStatus={errors.password ? 'error' : undefined}
                    help={errors.password}
                >
                    <Input.Password
                        prefix={<LockOutlined />}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Konfirmasi
                    </Button>
                </Form.Item>
            </Form>
        </GuestLayout>
    );
}
