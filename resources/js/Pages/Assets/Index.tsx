import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { PlusOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';

const STATUS_LABELS: Record<string, string> = {
    active: 'Aktif',
    fully_depreciated: 'Susut Penuh',
};

const STATUS_COLORS: Record<string, string> = {
    active: 'green',
    fully_depreciated: 'default',
};

interface Props {
    assets: {
        data: any[];
        current_page: number;
        per_page: number;
        total: number;
    };
}

export default function AssetsIndex({ assets }: Props) {
    const onPage = (page: number) => {
        router.get(route('assets.index'), { page }, { preserveState: true });
    };

    const columns = [
        { title: 'No.', dataIndex: 'asset_no', key: 'asset_no' },
        { title: 'Nama', dataIndex: 'name', key: 'name' },
        {
            title: 'Harga Perolehan',
            dataIndex: 'cost',
            key: 'cost',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Tgl Perolehan',
            dataIndex: 'acquisition_date',
            key: 'acquisition_date',
            render: (v: string) => (v ? dayjs(v).format('DD-MMM-YYYY') : '-'),
        },
        {
            title: 'Masa Manfaat',
            dataIndex: 'useful_life_months',
            key: 'useful_life_months',
            render: (v: number) => `${v} bulan`,
        },
        {
            title: 'Akumulasi Susut',
            dataIndex: 'accumulated_depreciation',
            key: 'accumulated_depreciation',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'Nilai Buku',
            key: 'book_value',
            align: 'right' as const,
            render: (_: unknown, r: any) => formatIDR(r.book_value),
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
            width: 100,
            render: (_: unknown, r: any) => (
                <Button size="small" onClick={() => router.get(route('assets.show', r.id))}>
                    Detail
                </Button>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Aset Tetap" />

            <Space style={{ marginBottom: 16, justifyContent: 'space-between', width: '100%' }} wrap>
                <Space wrap>
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => router.get(route('assets.create'))}
                    >
                        Tambah Aset
                    </Button>
                    <Button onClick={() => router.get(route('assets.depreciation'))}>
                        Penyusutan
                    </Button>
                </Space>
            </Space>

            <Table
                rowKey="id"
                columns={columns}
                dataSource={assets.data}
                pagination={{
                    current: assets.current_page,
                    pageSize: assets.per_page,
                    total: assets.total,
                    onChange: onPage,
                    showTotal: (t) => `Total ${t} aset`,
                }}
            />
        </AppLayout>
    );
}
