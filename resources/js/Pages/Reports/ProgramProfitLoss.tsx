import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Card, Col, Row, Statistic, Table, Tag, theme } from 'antd';
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
    data: {
        programs: ProgramRow[];
        total_revenue: number;
        total_expense: number;
        total_profit: number;
    };
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

export default function ProgramProfitLoss({ data }: Props) {
    const { token } = theme.useToken();

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
            render: (v: number) => (
                <span style={{ color: v >= 0 ? token.colorSuccess : token.colorError }}>
                    {formatIDR(v)}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Laba Rugi Program" />

            <Card
                title="Laba Rugi Program"
                extra={
                    <Button
                        icon={<DownloadOutlined />}
                        onClick={() =>
                            window.open(route('reports.export.program-profit-loss'))
                        }
                    >
                        Export Excel
                    </Button>
                }
            >
                <Row gutter={16} style={{ marginBottom: 16 }}>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Total Pendapatan"
                            value={data.total_revenue}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{ color: token.colorSuccess }}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Total Biaya"
                            value={data.total_expense}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{ color: token.colorError }}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Total Laba"
                            value={data.total_profit}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{
                                color: data.total_profit >= 0 ? token.colorSuccess : token.colorError,
                            }}
                        />
                    </Col>
                </Row>

                <Table
                    rowKey="id"
                    columns={columns}
                    dataSource={data.programs}
                    pagination={false}
                    size="small"
                    onRow={(r) => ({
                        onClick: () => router.get(route('programs.show', r.id)),
                        style: { cursor: 'pointer' },
                    })}
                />
            </Card>
        </AppLayout>
    );
}
