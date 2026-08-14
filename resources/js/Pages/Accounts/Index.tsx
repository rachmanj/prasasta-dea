import AppLayout from '@/Components/AppLayout';
import { router, useForm } from '@inertiajs/react';
import {
    Button,
    Form,
    Input,
    Modal,
    Popconfirm,
    Select,
    Space,
    Switch,
    Table,
    Tag,
} from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { useState } from 'react';

const TYPE_LABELS: Record<string, string> = {
    asset: 'Aset',
    liability: 'Kewajiban',
    equity: 'Ekuitas',
    revenue: 'Pendapatan',
    expense: 'Beban',
};

const TYPE_COLORS: Record<string, string> = {
    asset: 'blue',
    liability: 'orange',
    equity: 'purple',
    revenue: 'green',
    expense: 'red',
};

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
    is_bank: boolean;
    bank_name?: string;
    account_number?: string;
    is_active: boolean;
}

export default function AccountsIndex({ accounts }: { accounts: Account[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);

    const { data, setData, post, patch, processing, reset, errors, clearErrors } =
        useForm({
            code: '',
            name: '',
            type: 'asset',
            is_bank: false,
            bank_name: '',
            account_number: '',
            is_active: true,
        });

    const openCreate = () => {
        setEditing(null);
        reset();
        setOpen(true);
    };

    const openEdit = (acc: Account) => {
        setEditing(acc);
        setData({
            code: acc.code,
            name: acc.name,
            type: acc.type,
            is_bank: acc.is_bank,
            bank_name: acc.bank_name || '',
            account_number: acc.account_number || '',
            is_active: acc.is_active,
        });
        setOpen(true);
    };

    const submit = () => {
        if (editing) {
            patch(route('accounts.update', editing.id), {
                onSuccess: () => setOpen(false),
            });
        } else {
            post(route('accounts.store'), {
                onSuccess: () => setOpen(false),
            });
        }
    };

    const columns = [
        { title: 'Kode', dataIndex: 'code', key: 'code', width: 100 },
        { title: 'Nama Akun', dataIndex: 'name', key: 'name' },
        {
            title: 'Tipe',
            dataIndex: 'type',
            key: 'type',
            render: (t: string) => <Tag color={TYPE_COLORS[t]}>{TYPE_LABELS[t]}</Tag>,
        },
        {
            title: 'Rekening',
            dataIndex: 'is_bank',
            key: 'is_bank',
            render: (_: boolean, r: Account) =>
                r.is_bank ? (r.bank_name || 'Bank') + (r.account_number ? ` · ${r.account_number}` : '') : '-',
        },
        {
            title: 'Status',
            dataIndex: 'is_active',
            key: 'is_active',
            render: (v: boolean) =>
                v ? <Tag color="green">Aktif</Tag> : <Tag>Nonaktif</Tag>,
        },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 150,
            render: (_: unknown, r: Account) => (
                <Space>
                    <Button size="small" onClick={() => openEdit(r)}>
                        Edit
                    </Button>
                    <Popconfirm
                        title="Hapus akun ini?"
                        onConfirm={() => router.delete(route('accounts.destroy', r.id))}
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
                    Tambah Akun
                </Button>
            </Space>

            <Table rowKey="id" columns={columns} dataSource={accounts} pagination={false} />

            <Modal
                title={editing ? 'Edit Akun' : 'Tambah Akun'}
                open={open}
                onCancel={() => setOpen(false)}
                onOk={submit}
                confirmLoading={processing}
                destroyOnClose
            >
                <Form layout="vertical">
                    <Form.Item label="Kode Akun" required validateStatus={errors.code ? 'error' : undefined} help={errors.code}>
                        <Input value={data.code} onChange={(e) => setData('code', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Nama Akun" required validateStatus={errors.name ? 'error' : undefined} help={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Tipe">
                        <Select value={data.type} onChange={(v) => setData('type', v)} options={Object.entries(TYPE_LABELS).map(([k, v]) => ({ value: k, label: v }))} />
                    </Form.Item>
                    <Form.Item label="Rekening Bank" valuePropName="checked">
                        <Switch checked={data.is_bank} onChange={(v) => setData('is_bank', v)} />
                    </Form.Item>
                    {data.is_bank && (
                        <>
                            <Form.Item label="Nama Bank">
                                <Input value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)} placeholder="BCA / BRI / Mandiri" />
                            </Form.Item>
                            <Form.Item label="Nomor Rekening">
                                <Input value={data.account_number} onChange={(e) => setData('account_number', e.target.value)} />
                            </Form.Item>
                        </>
                    )}
                    <Form.Item label="Aktif" valuePropName="checked">
                        <Switch checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
