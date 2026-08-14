import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Card, Col, DatePicker, Row, Space, Statistic, Table } from 'antd';
import dayjs from 'dayjs';

interface Props {
    start: string;
    end: string;
    data: {
        revenue: { code: string; name: string; net: number }[];
        expense: { code: string; name: string; net: number }[];
        total_revenue: number;
        total_expense: number;
        profit: number;
    };
}

export default function ProfitLoss({ start, end, data }: Props) {
    const onRange = (dates: any) => {
        router.get(
            route('reports.profit-loss'),
            {
                start: dates?.[0] ? dates[0].format('YYYY-MM-DD') : undefined,
                end: dates?.[1] ? dates[1].format('YYYY-MM-DD') : undefined,
            },
            { preserveState: true },
        );
    };

    const columns = [
        { title: 'Akun', key: 'akun', render: (_: unknown, r: any) => `${r.code} - ${r.name}` },
        {
            title: 'Nominal',
            dataIndex: 'net',
            key: 'net',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
    ];

    return (
        <AppLayout>
            <Head title="Laporan Laba Rugi" />

            <Card
                title="Laporan Laba Rugi"
                extra={
                    <Space>
                        <DatePicker.RangePicker
                            value={[dayjs(start), dayjs(end)]}
                            onChange={onRange}
                        />
                        <Button
                            icon={<DownloadOutlined />}
                            onClick={() =>
                                window.open(
                                    route('reports.export.profit-loss', { start, end }),
                                )
                            }
                        >
                            Export Excel
                        </Button>
                    </Space>
                }
            >
                <Row gutter={16} style={{ marginBottom: 16 }}>
                    <Col xs={24} sm={8}>
                        <Statistic title="Total Pendapatan" value={data.total_revenue} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: '#52c41a' }} />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic title="Total Beban" value={data.total_expense} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: '#ff4d4f' }} />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic title="Laba (Rugi) Bersih" value={data.profit} formatter={(v) => formatIDR(Number(v))} />
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col xs={24} lg={12}>
                        <Card size="small" title="Pendapatan">
                            <Table rowKey="code" columns={columns} dataSource={data.revenue} pagination={false} size="small" />
                        </Card>
                    </Col>
                    <Col xs={24} lg={12}>
                        <Card size="small" title="Beban">
                            <Table rowKey="code" columns={columns} dataSource={data.expense} pagination={false} size="small" />
                        </Card>
                    </Col>
                </Row>
            </Card>
        </AppLayout>
    );
}
