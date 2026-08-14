import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { PlusOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';

const STATUS_LABELS: Record<string, string> = {
    open: 'Belum Lunas',
    partial: 'Sebagian',
    paid: 'Lunas',
};

const STATUS_COLORS: Record<string, string> = {
    open: 'red',
    partial: 'orange',
    paid: 'green',
};

interface Props {
    bills: {
        data: any[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { type: string; status?: string };
}

export default function BillsIndex({ bills, filters }: Props) {
    const type = filters.type || 'receivable';
    const title = type === 'receivable' ? 'Piutang' : 'Hutang';

    const onPage = (page: number) => {
        router.get(
            route('bills.index'),
            { type, status: filters.status, page },
            { preserveState: true },
        );
    };

    const columns = [
        { title: 'No. Tagihan', dataIndex: 'bill_no', key: 'bill_no' },
        { title: 'Tanggal', dataIndex: 'date', key: 'date' },
        {
            title: type === 'receivable' ? 'Pihak yang Berhutang' : 'Pihak yang Ditagih',
            key: 'contact',
            render: (_: unknown, r: any) => r.contact?.name ?? '-',
        },
        { title: 'Keterangan', dataIndex: 'description', key: 'description', render: (v?: string) => v || '-' },
        {
            title: 'Jatuh Tempo',
            dataIndex: 'due_date',
            key: 'due_date',
            render: (v: string) => v,
        },
        {
            title: 'Total',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Sisa',
            key: 'remaining',
            align: 'right' as const,
            render: (_: unknown, r: any) => formatIDR(Number(r.amount) - Number(r.paid_amount)),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (s: string) => <Tag color={STATUS_COLORS[s]}>{STATUS_LABELS[s]}</Tag>,
        },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 90,
            render: (_: unknown, r: any) => (
                <Button size="small" onClick={() => router.get(route('bills.show', r.id))}>
                    Detail
                </Button>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={title} />

            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%' }}>
                <Button type="primary" icon={<PlusOutlined />} onClick={() => router.get(route('bills.create', { type }))}>
                    Tambah {title}
                </Button>
            </Space>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={bills.data}
                pagination={{
                    current: bills.current_page,
                    pageSize: bills.per_page,
                    total: bills.total,
                    onChange: onPage,
                    showTotal: (t) => `Total ${t} tagihan`,
                }}
            />
        </AppLayout>
    );
}
