import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { PlusOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Select, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';

const STATUS_LABELS: Record<string, string> = {
    open: 'Terbuka',
    partial: 'Sebagian',
    settled: 'Lunas',
};

const STATUS_COLORS: Record<string, string> = {
    open: 'red',
    partial: 'orange',
    settled: 'green',
};

interface Employee {
    id: number;
    name: string;
}

interface Props {
    advances: {
        data: any[];
        current_page: number;
        per_page: number;
        total: number;
    };
    employees: Employee[];
    filters: { contact_id?: string; status?: string };
}

export default function CashAdvancesIndex({ advances, employees, filters }: Props) {
    const onPage = (page: number) => {
        router.get(
            route('cash-advances.index'),
            { contact_id: filters.contact_id, status: filters.status, page },
            { preserveState: true },
        );
    };

    const columns = [
        { title: 'No.', dataIndex: 'advance_no', key: 'advance_no' },
        {
            title: 'Tanggal',
            dataIndex: 'date',
            key: 'date',
            render: (v: string) => (v ? dayjs(v).format('DD-MMM-YYYY') : '-'),
        },
        {
            title: 'Karyawan',
            key: 'contact',
            render: (_: unknown, r: any) => r.contact?.name ?? '-',
        },
        {
            title: 'Nominal',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Terealisasi',
            key: 'cleared',
            align: 'right' as const,
            render: (_: unknown, r: any) =>
                formatIDR(Number(r.realized_amount) + Number(r.returned_amount)),
        },
        {
            title: 'Sisa',
            key: 'remaining',
            align: 'right' as const,
            render: (_: unknown, r: any) =>
                formatIDR(Number(r.amount) - Number(r.realized_amount) - Number(r.returned_amount)),
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
            width: 160,
            render: (_: unknown, r: any) => (
                <Space>
                    <Button size="small" onClick={() => router.get(route('cash-advances.show', r.id))}>
                        Detail
                    </Button>
                    {r.status !== 'settled' && (
                        <Button
                            size="small"
                            type="primary"
                            onClick={() => router.get(route('cash-advances.realize.create', r.id))}
                        >
                            Realisasi
                        </Button>
                    )}
                </Space>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Kas Bon" />

            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%' }} wrap>
                <Button
                    type="primary"
                    icon={<PlusOutlined />}
                    onClick={() => router.get(route('cash-advances.create'))}
                >
                    Tambah Kas Bon
                </Button>
                <Space wrap>
                    <Select
                        allowClear
                        placeholder="Filter karyawan"
                        style={{ minWidth: 200 }}
                        value={filters.contact_id ? Number(filters.contact_id) : undefined}
                        onChange={(v) =>
                            router.get(route('cash-advances.index'), {
                                contact_id: v ?? undefined,
                                status: filters.status,
                            })
                        }
                        options={employees.map((e) => ({ value: e.id, label: e.name }))}
                    />
                    <Select
                        allowClear
                        placeholder="Filter status"
                        style={{ minWidth: 160 }}
                        value={filters.status}
                        onChange={(v) =>
                            router.get(route('cash-advances.index'), {
                                contact_id: filters.contact_id,
                                status: v ?? undefined,
                            })
                        }
                        options={Object.entries(STATUS_LABELS).map(([k, v]) => ({ value: k, label: v }))}
                    />
                </Space>
            </Space>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={advances.data}
                pagination={{
                    current: advances.current_page,
                    pageSize: advances.per_page,
                    total: advances.total,
                    onChange: onPage,
                    showTotal: (t) => `Total ${t} kas bon`,
                }}
            />
        </AppLayout>
    );
}
