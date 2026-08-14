import GuestLayout from '@/Components/GuestLayout';
import { LockOutlined, MailOutlined, UserOutlined } from '@ant-design/icons';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Form, Input } from 'antd';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => post(route('register'));

    return (
        <GuestLayout>
            <Head title="Register" />

            <Form layout="vertical" onFinish={submit}>
                <Form.Item
                    label="Nama"
                    validateStatus={errors.name ? 'error' : undefined}
                    help={errors.name}
                >
                    <Input
                        prefix={<UserOutlined />}
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoComplete="name"
                        autoFocus
                    />
                </Form.Item>

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
                        autoComplete="username"
                    />
                </Form.Item>

                <Form.Item
                    label="Password"
                    validateStatus={errors.password ? 'error' : undefined}
                    help={errors.password}
                >
                    <Input.Password
                        prefix={<LockOutlined />}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
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
                        Daftar
                    </Button>
                </Form.Item>
            </Form>

            <div style={{ textAlign: 'center' }}>
                Sudah punya akun?{' '}
                <Link href={route('login')}>Masuk</Link>
            </div>
        </GuestLayout>
    );
}
