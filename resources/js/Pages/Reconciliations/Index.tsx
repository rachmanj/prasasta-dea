import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { PlusOutlined } from '@ant-design/icons';
import { Head, Link, router } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    in_review: 'Dalam Review',
    completed: 'Selesai',
};

const STATUS_COLORS: Record<string, string> = {
    draft: 'default',
    in_review: 'processing',
    completed: 'success',
};

interface Reconciliation {
    id: number;
    period: string;
    status: string;
    opening_balance_bank: number | string | null;
    closing_balance_bank: number | string | null;
    closing_balance_book: number | string | null;
    account?: { code: string; name: string };
}

interface Props {
    reconciliations: {
        data: Reconciliation[];
        current_page: number;
        per_page: number;
        total: number;
    };
}

export default function ReconciliationsIndex({ reconciliations }: Props) {
    const columns = [
        {
            title: 'Periode',
            key: 'period',
            render: (_: unknown, r: Reconciliation) =>
                dayjs(r.period).format('MMMM YYYY'),
        },
        {
            title: 'Rekening',
            key: 'account',
            render: (_: unknown, r: Reconciliation) =>
                r.account ? `${r.account.code} - ${r.account.name}` : '-',
        },
        {
            title: 'Saldo Bank (Penutup)',
            key: 'closing_bank',
            align: 'right' as const,
            render: (_: unknown, r: Reconciliation) =>
                formatIDR(r.closing_balance_bank ?? 0),
        },
        {
            title: 'Saldo Buku (Penutup)',
            key: 'closing_book',
            align: 'right' as const,
            render: (_: unknown, r: Reconciliation) =>
                formatIDR(r.closing_balance_book ?? 0),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (v: string) => (
                <Tag color={STATUS_COLORS[v] ?? 'default'}>
                    {STATUS_LABELS[v] ?? v}
                </Tag>
            ),
        },
        {
            title: '',
            key: 'action',
            render: (_: unknown, r: Reconciliation) => (
                <Link href={route('reconciliations.show', r.id)}>Buka</Link>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Rekonsiliasi Bank" />

            <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between' }}>
                <h2 style={{ margin: 0 }}>Rekonsiliasi Bank</h2>
                <Link href={route('reconciliations.create')}>
                    <Button type="primary" icon={<PlusOutlined />}>
                        Mulai Rekonsiliasi
                    </Button>
                </Link>
            </div>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={reconciliations.data}
                pagination={{
                    current: reconciliations.current_page,
                    pageSize: reconciliations.per_page,
                    total: reconciliations.total,
                    onChange: (page) =>
                        router.get(route('reconciliations.index'), { page }, { preserveState: true }),
                }}
                size="small"
            />
        </AppLayout>
    );
}
