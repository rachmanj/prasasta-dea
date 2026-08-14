import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { DownloadOutlined } from '@ant-design/icons';
import { Head } from '@inertiajs/react';
import { Button, Card, Col, Row, Statistic, Table, Tag } from 'antd';

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
    data: {
        receivables: any[];
        payables: any[];
        total_receivable: number;
        total_payable: number;
    };
}

export default function ReceivablesPayables({ data }: Props) {
    const columns = (keyPrefix: string) => [
        { title: 'No. Tagihan', dataIndex: 'bill_no', key: `${keyPrefix}-no` },
        {
            title: 'Kontak',
            key: `${keyPrefix}-contact`,
            render: (_: unknown, r: any) => r.contact?.name ?? '-',
        },
        { title: 'Jatuh Tempo', dataIndex: 'due_date', key: `${keyPrefix}-due` },
        {
            title: 'Sisa',
            key: `${keyPrefix}-remaining`,
            align: 'right' as const,
            render: (_: unknown, r: any) => formatIDR(Number(r.amount) - Number(r.paid_amount)),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: `${keyPrefix}-status`,
            render: (s: string) => <Tag color={STATUS_COLORS[s]}>{STATUS_LABELS[s]}</Tag>,
        },
    ];

    return (
        <AppLayout>
            <Head title="Posisi Hutang & Piutang" />

            <Card
                title="Posisi Hutang & Piutang"
                extra={
                    <Button
                        icon={<DownloadOutlined />}
                        onClick={() =>
                            window.open(route('reports.export.receivables-payables'))
                        }
                    >
                        Export Excel
                    </Button>
                }
            >
                <Row gutter={16} style={{ marginBottom: 16 }}>
                    <Col xs={24} sm={12}>
                        <Statistic title="Total Piutang" value={data.total_receivable} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: '#faad14' }} />
                    </Col>
                    <Col xs={24} sm={12}>
                        <Statistic title="Total Hutang" value={data.total_payable} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: '#faad14' }} />
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col xs={24} lg={12}>
                        <Card size="small" title="Piutang">
                            <Table rowKey="id" columns={columns('recv')} dataSource={data.receivables} pagination={false} size="small" />
                        </Card>
                    </Col>
                    <Col xs={24} lg={12}>
                        <Card size="small" title="Hutang">
                            <Table rowKey="id" columns={columns('pay')} dataSource={data.payables} pagination={false} size="small" />
                        </Card>
                    </Col>
                </Row>
            </Card>
        </AppLayout>
    );
}
