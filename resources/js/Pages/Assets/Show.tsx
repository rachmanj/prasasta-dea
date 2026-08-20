import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import {
    Button,
    Card,
    Col,
    Descriptions,
    Popconfirm,
    Row,
    Space,
    Statistic,
    Table,
    Tag,
} from 'antd';
import dayjs from 'dayjs';

const STATUS_LABELS: Record<string, string> = {
    active: 'Aktif',
    fully_depreciated: 'Susut Penuh',
};

const STATUS_COLORS: Record<string, string> = {
    active: 'green',
    fully_depreciated: 'default',
};

export default function AssetShow({ asset }: { asset: any }) {
    const journalColumns = [
        {
            title: 'Akun',
            key: 'account',
            render: (_: unknown, r: any) =>
                r.account ? `${r.account.code} - ${r.account.name}` : '-',
        },
        {
            title: 'Debit',
            key: 'debit',
            align: 'right' as const,
            render: (_: unknown, r: any) => (Number(r.debit) > 0 ? formatIDR(r.debit) : '-'),
        },
        {
            title: 'Kredit',
            key: 'credit',
            align: 'right' as const,
            render: (_: unknown, r: any) => (Number(r.credit) > 0 ? formatIDR(r.credit) : '-'),
        },
    ];

    const depreciationColumns = [
        {
            title: 'Tanggal',
            dataIndex: 'date',
            key: 'date',
            render: (v: string) => (v ? dayjs(v).format('DD-MMM-YYYY') : '-'),
        },
        {
            title: 'Nominal',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'No. Jurnal',
            key: 'journal',
            render: (_: unknown, r: any) => r.transaction?.journal_no ?? '-',
        },
    ];

    return (
        <AppLayout>
            <Head title={asset.asset_no} />

            <Row gutter={[16, 16]}>
                <Col span={24}>
                    <Card
                        title={`Aset ${asset.asset_no}`}
                        extra={
                            <Popconfirm
                                title="Hapus aset ini?"
                                onConfirm={() => router.delete(route('assets.destroy', asset.id))}
                            >
                                <Button danger>Hapus</Button>
                            </Popconfirm>
                        }
                    >
                        <Descriptions column={2} bordered size="small">
                            <Descriptions.Item label="Nama">{asset.name}</Descriptions.Item>
                            <Descriptions.Item label="Status">
                                <Tag color={STATUS_COLORS[asset.status]}>
                                    {STATUS_LABELS[asset.status]}
                                </Tag>
                            </Descriptions.Item>
                            <Descriptions.Item label="Tanggal Perolehan">
                                {asset.acquisition_date
                                    ? dayjs(asset.acquisition_date).format('DD-MMM-YYYY')
                                    : '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="Masa Manfaat">
                                {asset.useful_life_months} bulan
                            </Descriptions.Item>
                            <Descriptions.Item label="Penyusutan Bulanan">
                                {formatIDR(asset.monthly_depreciation)}
                            </Descriptions.Item>
                        </Descriptions>

                        <Row gutter={16} style={{ marginTop: 16 }}>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Harga Perolehan"
                                    value={Number(asset.cost)}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Akumulasi Penyusutan"
                                    value={Number(asset.accumulated_depreciation)}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Nilai Buku"
                                    value={Number(asset.book_value)}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                        </Row>
                    </Card>
                </Col>
            </Row>

            <Card title="Jurnal Perolehan" style={{ marginTop: 16 }}>
                <Table
                    size="small"
                    rowKey="id"
                    columns={journalColumns}
                    dataSource={asset.transaction?.journal_entries || []}
                    pagination={false}
                />
            </Card>

            <Card title="Riwayat Penyusutan" style={{ marginTop: 16 }}>
                <Table
                    rowKey="id"
                    columns={depreciationColumns}
                    dataSource={asset.depreciations}
                    pagination={false}
                    size="small"
                    expandable={{
                        expandedRowRender: (r: any) => (
                            <Table
                                size="small"
                                rowKey={(e: any) => e.id}
                                columns={journalColumns}
                                dataSource={r.transaction?.journal_entries || []}
                                pagination={false}
                            />
                        ),
                    }}
                />
            </Card>
        </AppLayout>
    );
}
