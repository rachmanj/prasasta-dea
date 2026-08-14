import AppLayout from '@/Components/AppLayout';
import { PlusOutlined } from '@ant-design/icons';
import { router, useForm } from '@inertiajs/react';
import {
    Button,
    Form,
    Input,
    Modal,
    Popconfirm,
    Select,
    Space,
    Table,
    Tag,
} from 'antd';
import { useState } from 'react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    roles: { name: string }[];
}

export default function UsersIndex({
    users,
    roles,
}: {
    users: UserRow[];
    roles: string[];
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);

    const { data, setData, post, patch, processing, reset, errors } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'bendahara',
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setOpen(true);
    };

    const openEdit = (u: UserRow) => {
        setEditing(u);
        setData({
            name: u.name,
            email: u.email,
            password: '',
            role: u.roles[0]?.name || 'bendahara',
        });
        setOpen(true);
    };

    const submit = () => {
        if (editing) {
            patch(route('users.update', editing.id), { onSuccess: () => setOpen(false) });
        } else {
            post(route('users.store'), { onSuccess: () => setOpen(false) });
        }
    };

    const columns = [
        { title: 'Nama', dataIndex: 'name', key: 'name' },
        { title: 'Email', dataIndex: 'email', key: 'email' },
        {
            title: 'Role',
            key: 'role',
            render: (_: unknown, r: UserRow) =>
                r.roles.map((role) => <Tag color="blue" key={role.name}>{role.name}</Tag>),
        },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 150,
            render: (_: unknown, r: UserRow) => (
                <Space>
                    <Button size="small" onClick={() => openEdit(r)}>
                        Edit
                    </Button>
                    <Popconfirm
                        title="Hapus user ini?"
                        onConfirm={() => router.delete(route('users.destroy', r.id))}
                    >
                        <Button size="small" danger>
                            Hapus
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <AppLayout>
            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%' }}>
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
                    Tambah User
                </Button>
            </Space>

            <Table rowKey="id" columns={columns} dataSource={users} pagination={false} />

            <Modal
                title={editing ? 'Edit User' : 'Tambah User'}
                open={open}
                onCancel={() => setOpen(false)}
                onOk={submit}
                confirmLoading={processing}
                destroyOnClose
            >
                <Form layout="vertical">
                    <Form.Item label="Nama" required validateStatus={errors.name ? 'error' : undefined} help={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Email" required validateStatus={errors.email ? 'error' : undefined} help={errors.email}>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </Form.Item>
                    <Form.Item
                        label={editing ? 'Password (kosongkan jika tidak diubah)' : 'Password'}
                        required={!editing}
                        validateStatus={errors.password ? 'error' : undefined}
                        help={errors.password}
                    >
                        <Input.Password value={data.password} onChange={(e) => setData('password', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Role">
                        <Select
                            value={data.role}
                            onChange={(v) => setData('role', v)}
                            options={roles.map((r) => ({ value: r, label: r }))}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
