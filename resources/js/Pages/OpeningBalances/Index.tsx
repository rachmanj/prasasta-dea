import AppLayout from '@/Components/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Alert, Button, Card, DatePicker, Form, InputNumber, Space, Table } from 'antd';
import dayjs from 'dayjs';

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
    opening_balance: string | number;
}

interface Props {
    accounts: Account[];
    hasSaldoAwal: boolean;
}

const currencyFormatter = (v?: number | string) =>
    `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) =>
    Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function OpeningBalancesIndex({ accounts, hasSaldoAwal }: Props) {
    const initialBalances: Record<number, number> = {};
    accounts.forEach((a) => {
        initialBalances[a.id] = Number(a.opening_balance) || 0;
    });

    const { data, setData, post, processing, errors } = useForm({
        balances: initialBalances,
        date: dayjs().format('YYYY-MM-DD'),
    });

    const submit = () => {
        post(route('opening-balances.store'));
    };

    const columns = [
        {
            title: 'Kode',
            dataIndex: 'code',
            key: 'code',
            width: 100,
        },
        {
            title: 'Nama Akun',
            dataIndex: 'name',
            key: 'name',
        },
        {
            title: 'Saldo Awal',
            key: 'balance',
            width: 220,
            render: (_: unknown, record: Account) => (
                <InputNumber
                    style={{ width: '100%' }}
                    min={0}
                    formatter={currencyFormatter}
                    parser={currencyParser}
                    value={data.balances[record.id] ?? 0}
                    onChange={(v) =>
                        setData('balances', {
                            ...data.balances,
                            [record.id]: Number(v) || 0,
                        })
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Saldo Awal" />

            <Card title="Saldo Awal">
                {hasSaldoAwal && (
                    <Alert
                        type="warning"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="Saldo awal sudah pernah disimpan. Menyimpan ulang akan mengganti jurnal saldo awal yang ada."
                    />
                )}

                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Tanggal Saldo Awal"
                        validateStatus={errors.date ? 'error' : undefined}
                        help={errors.date}
                    >
                        <DatePicker
                            value={dayjs(data.date)}
                            onChange={(d) =>
                                setData('date', d ? d.format('YYYY-MM-DD') : '')
                            }
                            style={{ width: 200 }}
                            format="DD/MM/YYYY"
                        />
                    </Form.Item>

                    <Table
                        rowKey="id"
                        columns={columns}
                        dataSource={accounts}
                        pagination={false}
                        size="small"
                        style={{ marginBottom: 16 }}
                    />

                    <Space>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Simpan Saldo Awal
                        </Button>
                    </Space>
                </Form>
            </Card>
        </AppLayout>
    );
}
