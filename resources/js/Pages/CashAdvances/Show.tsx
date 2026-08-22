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
    theme,
} from 'antd';
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

export default function CashAdvanceShow({ advance }: { advance: any }) {
    const { token } = theme.useToken();
    const remaining =
        Number(advance.amount) - Number(advance.realized_amount) - Number(advance.returned_amount);
    const cleared = Number(advance.realized_amount) + Number(advance.returned_amount);

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

    const realizationColumns = [
        {
            title: 'Tanggal',
            dataIndex: 'date',
            key: 'date',
            render: (v: string) => (v ? dayjs(v).format('DD-MMM-YYYY') : '-'),
        },
        { title: 'Keterangan', dataIndex: 'description', key: 'description', render: (v?: string) => v || '-' },
        {
            title: 'Program',
            key: 'program',
            render: (_: unknown, r: any) => r.program?.name ?? '-',
        },
        {
            title: 'No. Jurnal',
            key: 'journal',
            render: (_: unknown, r: any) => r.transaction?.journal_no ?? '-',
        },
    ];

    return (
        <AppLayout>
            <Head title={advance.advance_no} />

            <Row gutter={[16, 16]}>
                <Col span={24}>
                    <Card
                        title={`Kas Bon ${advance.advance_no}`}
                        extra={
                            <Space>
                                {advance.status !== 'settled' && (
                                    <Button
                                        type="primary"
                                        onClick={() =>
                                            router.get(route('cash-advances.realize.create', advance.id))
                                        }
                                    >
                                        Realisasi
                                    </Button>
                                )}
                                <Popconfirm
                                    title="Hapus kas bon ini?"
                                    onConfirm={() => router.delete(route('cash-advances.destroy', advance.id))}
                                >
                                    <Button danger>Hapus</Button>
                                </Popconfirm>
                            </Space>
                        }
                    >
                        <Descriptions column={2} bordered size="small">
                            <Descriptions.Item label="Karyawan">{advance.contact?.name}</Descriptions.Item>
                            <Descriptions.Item label="Status">
                                <Tag color={STATUS_COLORS[advance.status]}>
                                    {STATUS_LABELS[advance.status]}
                                </Tag>
                            </Descriptions.Item>
                            <Descriptions.Item label="Tanggal">
                                {advance.date ? dayjs(advance.date).format('DD-MMM-YYYY') : '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="Keperluan">{advance.description || '-'}</Descriptions.Item>
                            {advance.program?.name && (
                                <Descriptions.Item label="Program">{advance.program.name}</Descriptions.Item>
                            )}
                        </Descriptions>

                        <Row gutter={16} style={{ marginTop: 16 }}>
                            <Col xs={24} sm={6}>
                                <Statistic
                                    title="Nominal"
                                    value={Number(advance.amount)}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={6}>
                                <Statistic
                                    title="Terealisasi"
                                    value={cleared}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={6}>
                                <Statistic
                                    title="Beban"
                                    value={Number(advance.realized_amount)}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={6}>
                                <Statistic
                                    title="Sisa"
                                    value={remaining}
                                    formatter={(v) => formatIDR(Number(v))}
                                    valueStyle={{ color: remaining > 0 ? token.colorWarning : token.colorSuccess }}
                                />
                            </Col>
                        </Row>
                    </Card>
                </Col>
            </Row>

            <Card title="Jurnal Kas Bon" style={{ marginTop: 16 }}>
                <Table
                    size="small"
                    rowKey="id"
                    columns={journalColumns}
                    dataSource={advance.transaction?.journal_entries || []}
                    pagination={false}
                />
            </Card>

            <Card title="Riwayat Realisasi" style={{ marginTop: 16 }}>
                <Table
                    rowKey="id"
                    columns={realizationColumns}
                    dataSource={advance.realizations}
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
