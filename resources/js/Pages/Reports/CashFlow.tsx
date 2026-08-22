import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Card, Col, DatePicker, Row, Space, Statistic, Table, theme } from 'antd';
import dayjs from 'dayjs';

interface Props {
    start: string;
    end: string;
    data: {
        total_inflow: number;
        total_outflow: number;
        net: number;
        inflow_by_category: Record<string, number>;
        outflow_by_category: Record<string, number>;
    };
}

export default function CashFlow({ start, end, data }: Props) {
    const { token } = theme.useToken();
    const inflowRows = Object.entries(data.inflow_by_category).map(([k, v]) => ({
        kategori: k,
        nominal: v,
    }));
    const outflowRows = Object.entries(data.outflow_by_category).map(([k, v]) => ({
        kategori: k,
        nominal: v,
    }));

    const onRange = (dates: any) => {
        router.get(
            route('reports.cash-flow'),
            {
                start: dates?.[0] ? dates[0].format('YYYY-MM-DD') : undefined,
                end: dates?.[1] ? dates[1].format('YYYY-MM-DD') : undefined,
            },
            { preserveState: true },
        );
    };

    const columns = [
        { title: 'Kategori', dataIndex: 'kategori', key: 'kategori' },
        {
            title: 'Nominal',
            dataIndex: 'nominal',
            key: 'nominal',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
    ];

    return (
        <AppLayout>
            <Head title="Laporan Arus Kas" />

            <Card
                title="Laporan Arus Kas"
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
                                    route('reports.export.cash-flow', { start, end }),
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
                        <Statistic title="Total Uang Masuk" value={data.total_inflow} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: token.colorSuccess }} />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic title="Total Uang Keluar" value={data.total_outflow} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: token.colorError }} />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic title="Arus Kas Bersih" value={data.net} formatter={(v) => formatIDR(Number(v))} />
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col xs={24} lg={12}>
                        <Card size="small" title="Uang Masuk per Kategori">
                            <Table rowKey="kategori" columns={columns} dataSource={inflowRows} pagination={false} size="small" />
                        </Card>
                    </Col>
                    <Col xs={24} lg={12}>
                        <Card size="small" title="Uang Keluar per Kategori">
                            <Table rowKey="kategori" columns={columns} dataSource={outflowRows} pagination={false} size="small" />
                        </Card>
                    </Col>
                </Row>
            </Card>
        </AppLayout>
    );
}
