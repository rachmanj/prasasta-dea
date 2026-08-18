import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { Card, Col, DatePicker, Row, Statistic, Table } from 'antd';
import dayjs from 'dayjs';

interface CashBalance {
    id: number;
    code: string;
    name: string;
    balance: number;
}

interface Props {
    start: string;
    end: string;
    cashBalances: CashBalance[];
    cashFlow: {
        total_inflow: number;
        total_outflow: number;
        net: number;
        inflow_by_category: Record<string, number>;
        outflow_by_category: Record<string, number>;
    };
    receivablesPayables: {
        total_receivable: number;
        total_payable: number;
    };
}

export default function Dashboard({ start, end, cashBalances, cashFlow, receivablesPayables }: Props) {
    const totalCash = cashBalances.reduce((s, a) => s + Number(a.balance), 0);

    const onRange = (dates: any) => {
        router.get(
            route('dashboard'),
            {
                start: dates?.[0] ? dates[0].format('YYYY-MM-DD') : undefined,
                end: dates?.[1] ? dates[1].format('YYYY-MM-DD') : undefined,
            },
            { preserveState: true },
        );
    };

    const columns = [
        { title: 'Kode', dataIndex: 'code', key: 'code', width: 100 },
        { title: 'Nama Akun', dataIndex: 'name', key: 'name' },
        {
            title: 'Saldo',
            dataIndex: 'balance',
            key: 'balance',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
    ];

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <Card style={{ marginBottom: 16 }}>
                <DatePicker.RangePicker value={[dayjs(start), dayjs(end)]} onChange={onRange} />
            </Card>

            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} lg={8}>
                    <Card>
                        <Statistic
                            title="Total Saldo Kas & Bank"
                            value={totalCash}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={8}>
                    <Card>
                        <Statistic
                            title="Uang Masuk (periode)"
                            value={cashFlow.total_inflow}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={8}>
                    <Card>
                        <Statistic
                            title="Uang Keluar (periode)"
                            value={cashFlow.total_outflow}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={8}>
                    <Card>
                        <Statistic
                            title="Total Piutang"
                            value={receivablesPayables.total_receivable}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={8}>
                    <Card>
                        <Statistic
                            title="Total Hutang"
                            value={receivablesPayables.total_payable}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Card>
                </Col>
            </Row>

            <Card style={{ marginTop: 16 }} title="Saldo Kas & Bank">
                <Table
                    rowKey="id"
                    columns={columns}
                    dataSource={cashBalances}
                    pagination={false}
                    size="small"
                />
            </Card>
        </AppLayout>
    );
}
