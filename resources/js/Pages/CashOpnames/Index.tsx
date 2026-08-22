import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { PlusOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';

interface StatusBadge {
    label: string;
    color: string;
}

interface Opname {
    id: number;
    number: string;
    date: string;
    book_balance: number | string;
    physical_balance: number | string;
    difference: number | string;
    status: string;
    status_badge: StatusBadge;
}

interface Props {
    opnames: {
        data: Opname[];
        current_page: number;
        per_page: number;
        total: number;
    };
    canCreate?: boolean;
}

export default function CashOpnamesIndex({ opnames, canCreate = false }: Props) {
    const columns = [
        { title: 'Nomor', dataIndex: 'number', key: 'number' },
        {
            title: 'Tanggal',
            dataIndex: 'date',
            key: 'date',
            render: (v: string) => (v ? dayjs(v).format('DD-MMM-YYYY') : '-'),
        },
        {
            title: 'Saldo Buku',
            dataIndex: 'book_balance',
            key: 'book_balance',
            align: 'right' as const,
            render: (v: number | string) => formatIDR(v),
        },
        {
            title: 'Saldo Fisik',
            dataIndex: 'physical_balance',
            key: 'physical_balance',
            align: 'right' as const,
            render: (v: number | string) => formatIDR(v),
        },
        {
            title: 'Selisih',
            dataIndex: 'difference',
            key: 'difference',
            align: 'right' as const,
            render: (v: number | string) => formatIDR(v),
        },
        {
            title: 'Status',
            key: 'status_badge',
            render: (_: unknown, r: Opname) => (
                <Tag color={r.status_badge?.color ?? 'default'}>
                    {r.status_badge?.label ?? r.status}
                </Tag>
            ),
        },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 100,
            render: (_: unknown, r: Opname) => (
                <Button size="small" onClick={() => router.get(route('cash-opnames.show', r.id))}>
                    Detail
                </Button>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Kas Opname" />

            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%' }} wrap>
                {canCreate ? (
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => router.get(route('cash-opnames.create'))}
                    >
                        Opname Baru
                    </Button>
                ) : (
                    <span />
                )}
            </Space>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={opnames.data}
                pagination={{
                    current: opnames.current_page,
                    pageSize: opnames.per_page,
                    total: opnames.total,
                    onChange: (page) =>
                        router.get(route('cash-opnames.index'), { page }, { preserveState: true }),
                    showTotal: (t) => `Total ${t} opname`,
                }}
            />
        </AppLayout>
    );
}
