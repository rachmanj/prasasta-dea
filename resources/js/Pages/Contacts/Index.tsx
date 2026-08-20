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

const TYPE_LABELS: Record<string, string> = {
    student: 'Siswa',
    vendor: 'Vendor',
    instructor: 'Pengajar',
    donor: 'Donatur',
    employee: 'Karyawan',
    other: 'Lainnya',
};

interface Contact {
    id: number;
    name: string;
    type: string;
    phone?: string;
    address?: string;
}

export default function ContactsIndex({ contacts }: { contacts: Contact[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Contact | null>(null);

    const { data, setData, post, patch, processing, reset, errors } = useForm({
        name: '',
        type: 'student',
        phone: '',
        address: '',
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setOpen(true);
    };

    const openEdit = (c: Contact) => {
        setEditing(c);
        setData({
            name: c.name,
            type: c.type,
            phone: c.phone || '',
            address: c.address || '',
        });
        setOpen(true);
    };

    const submit = () => {
        if (editing) {
            patch(route('contacts.update', editing.id), { onSuccess: () => setOpen(false) });
        } else {
            post(route('contacts.store'), { onSuccess: () => setOpen(false) });
        }
    };

    const columns = [
        { title: 'Nama', dataIndex: 'name', key: 'name' },
        {
            title: 'Tipe',
            dataIndex: 'type',
            key: 'type',
            render: (t: string) => <Tag>{TYPE_LABELS[t] ?? t}</Tag>,
        },
        { title: 'Telepon', dataIndex: 'phone', key: 'phone', render: (v?: string) => v || '-' },
        { title: 'Alamat', dataIndex: 'address', key: 'address', render: (v?: string) => v || '-' },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 150,
            render: (_: unknown, r: Contact) => (
                <Space>
                    <Button size="small" onClick={() => openEdit(r)}>
                        Edit
                    </Button>
                    <Popconfirm
                        title="Hapus kontak ini?"
                        onConfirm={() => router.delete(route('contacts.destroy', r.id))}
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
                    Tambah Kontak
                </Button>
            </Space>

            <Table rowKey="id" columns={columns} dataSource={contacts} pagination={false} />

            <Modal
                title={editing ? 'Edit Kontak' : 'Tambah Kontak'}
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
                    <Form.Item label="Tipe">
                        <Select
                            value={data.type}
                            onChange={(v) => setData('type', v)}
                            options={Object.entries(TYPE_LABELS).map(([k, v]) => ({ value: k, label: v }))}
                        />
                    </Form.Item>
                    <Form.Item label="Telepon">
                        <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Alamat">
                        <Input value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
