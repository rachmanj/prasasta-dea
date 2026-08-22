import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { PlusOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';

interface ProgramRow {
    id: number;
    code: string;
    name: string;
    type: string;
    start_date: string | null;
    end_date: string | null;
    status: string;
    revenue: number;
    expense: number;
    profit: number;
}

interface Props {
    programs: ProgramRow[];
    canManage?: boolean;
}

const TYPE_LABELS: Record<string, string> = {
    group: 'Kelompok',
    individual: 'Individu',
};

const STATUS_LABELS: Record<string, string> = {
    active: 'Aktif',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const STATUS_COLORS: Record<string, string> = {
    active: 'green',
    completed: 'blue',
    cancelled: 'default',
};

export default function ProgramsIndex({ programs, canManage = false }: Props) {
    const columns = [
        { title: 'Kode', dataIndex: 'code', key: 'code', width: 130 },
        { title: 'Nama Program', dataIndex: 'name', key: 'name' },
        {
            title: 'Jenis',
            dataIndex: 'type',
            key: 'type',
            width: 100,
            render: (v: string) => TYPE_LABELS[v] ?? v,
        },
        {
            title: 'Periode',
            key: 'periode',
            width: 200,
            render: (_: unknown, r: ProgramRow) => {
                const start = r.start_date ? dayjs(r.start_date).format('DD-MMM-YYYY') : '-';
                const end = r.end_date ? dayjs(r.end_date).format('DD-MMM-YYYY') : '-';
                return `${start} s/d ${end}`;
            },
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 110,
            render: (v: string) => (
                <Tag color={STATUS_COLORS[v]}>{STATUS_LABELS[v] ?? v}</Tag>
            ),
        },
        {
            title: 'Pendapatan',
            dataIndex: 'revenue',
            key: 'revenue',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Biaya',
            dataIndex: 'expense',
            key: 'expense',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Laba',
            dataIndex: 'profit',
            key: 'profit',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Aksi',
            key: 'aksi',
            width: 90,
            render: (_: unknown, r: ProgramRow) => (
                <Button size="small" onClick={() => router.get(route('programs.show', r.id))}>
                    Detail
                </Button>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Program Pelatihan" />

            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%' }} wrap>
                {canManage ? (
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => router.get(route('programs.create'))}
                    >
                        Program Baru
                    </Button>
                ) : (
                    <span />
                )}
            </Space>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={programs}
                pagination={{ pageSize: 20, showTotal: (t) => `Total ${t} program` }}
            />
        </AppLayout>
    );
}
