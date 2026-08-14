import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { DownloadOutlined, PlusOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, DatePicker, Popconfirm, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';

const TYPE_LABELS: Record<string, string> = {
    receipt: 'Uang Masuk',
    payment: 'Uang Keluar',
    transfer: 'Transfer',
    journal: 'Jurnal Umum',
};

const TYPE_COLORS: Record<string, string> = {
    receipt: 'green',
    payment: 'red',
    transfer: 'blue',
    journal: 'purple',
};

interface Props {
    transactions: {
        data: any[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { type: string; from?: string; to?: string };
}

export default function TransactionsIndex({ transactions, filters }: Props) {
    const type = filters.type || 'receipt';

    const onPage = (page: number) => {
        router.get(
            route('transactions.index'),
            { type, from: filters.from, to: filters.to, page },
            { preserveState: true },
        );
    };

    const onRange = (dates: any) => {
        router.get(
            route('transactions.index'),
            {
                type,
                from: dates?.[0] ? dates[0].format('YYYY-MM-DD') : undefined,
                to: dates?.[1] ? dates[1].format('YYYY-MM-DD') : undefined,
            },
            { preserveState: true },
        );
    };

    const describe = (tx: any) => {
        const entries = tx.journal_entries || [];
        const debit = entries.find((e: any) => Number(e.debit) > 0);
        const credit = entries.find((e: any) => Number(e.credit) > 0);

        if (tx.type === 'journal') {
            const total = entries.reduce((s: number, e: any) => s + Number(e.debit), 0);
            return { cash: '-', other: `${entries.length} baris jurnal`, amount: total };
        }

        if (tx.type === 'transfer') {
            return {
                cash: credit?.account?.name ?? '',
                other: debit?.account?.name ?? '',
                amount: Number(debit?.debit ?? 0),
                arrow: true,
            };
        }

        const cashLine = tx.type === 'receipt' ? debit : credit;
        const otherLine = tx.type === 'receipt' ? credit : debit;

        return {
            cash: cashLine?.account?.name ?? '',
            other: otherLine?.account?.name ?? '',
            amount: Number(debit?.debit ?? 0),
        };
    };

    const columns = [
        { title: 'No. Jurnal', dataIndex: 'journal_no', key: 'journal_no' },
        { title: 'Tanggal', dataIndex: 'date', key: 'date' },
        { title: 'Keterangan', dataIndex: 'description', key: 'description', render: (v?: string) => v || '-' },
        {
            title: 'Rekening',
            key: 'cash',
            render: (_: unknown, r: any) => describe(r).cash,
        },
        {
            title: 'Akun',
            key: 'other',
            render: (_: unknown, r: any) => {
                const d = describe(r);
                return d.arrow ? `${d.other} (tujuan)` : d.other;
            },
        },
        {
            title: 'Nominal',
            key: 'amount',
            align: 'right' as const,
            render: (_: unknown, r: any) => formatIDR(describe(r).amount),
        },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 150,
            render: (_: unknown, r: any) => (
                <Space>
                    <Button size="small" onClick={() => router.get(route('transactions.edit', r.id))}>
                        Edit
                    </Button>
                    <Popconfirm
                        title="Hapus transaksi ini?"
                        onConfirm={() => router.delete(route('transactions.destroy', r.id))}
                    >
                        <Button size="small" danger>
                            Hapus
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    const exportUrl = route('transactions.export', {
        type,
        from: filters.from,
        to: filters.to,
    });

    return (
        <AppLayout>
            <Head title={TYPE_LABELS[type]} />

            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%', flexWrap: 'wrap' }}>
                <Space>
                    <Tag color={TYPE_COLORS[type]} style={{ fontSize: 14, padding: '4px 10px' }}>
                        {TYPE_LABELS[type]}
                    </Tag>
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => router.get(route('transactions.create', { type }))}>
                        Tambah
                    </Button>
                </Space>
                <Space>
                    <DatePicker.RangePicker
                        value={[
                            filters.from ? dayjs(filters.from) : null,
                            filters.to ? dayjs(filters.to) : null,
                        ]}
                        onChange={onRange}
                    />
                    <Button icon={<DownloadOutlined />} onClick={() => window.open(exportUrl)}>
                        Export Excel
                    </Button>
                </Space>
            </Space>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={transactions.data}
                pagination={{
                    current: transactions.current_page,
                    pageSize: transactions.per_page,
                    total: transactions.total,
                    onChange: onPage,
                    showTotal: (t) => `Total ${t} transaksi`,
                }}
            />
        </AppLayout>
    );
}
