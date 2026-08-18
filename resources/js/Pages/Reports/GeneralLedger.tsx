import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Card, DatePicker, Select, Space, Statistic, Table, Typography } from 'antd';
import dayjs from 'dayjs';

interface Account {
    id: number;
    code: string;
    name: string;
}

interface LedgerEntry {
    date: string;
    journal_no: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
}

interface Props {
    accounts: Account[];
    accountId: number;
    start: string;
    end: string;
    data: {
        opening_balance: number;
        entries: LedgerEntry[];
        ending_balance: number;
    };
}

export default function GeneralLedger({ accounts, accountId, start, end, data }: Props) {
    const selectedAccount = accounts.find((a) => a.id === accountId);

    const reload = (nextAccountId: number, nextStart: string, nextEnd: string) => {
        router.get(
            route('reports.general-ledger'),
            { account_id: nextAccountId, start: nextStart, end: nextEnd },
            { preserveState: true },
        );
    };

    const onAccountChange = (value: number) => {
        reload(value, start, end);
    };

    const onRange = (dates: any) => {
        reload(
            accountId,
            dates?.[0] ? dates[0].format('YYYY-MM-DD') : start,
            dates?.[1] ? dates[1].format('YYYY-MM-DD') : end,
        );
    };

    const moneyCell = (v: number) => (Number(v) > 0 ? formatIDR(v) : '-');

    const columns = [
        {
            title: 'Tanggal',
            dataIndex: 'date',
            key: 'date',
            render: (v: string) => dayjs(v).format('DD-MMM-YYYY'),
        },
        { title: 'No. Jurnal', dataIndex: 'journal_no', key: 'journal_no' },
        {
            title: 'Keterangan',
            dataIndex: 'description',
            key: 'description',
            render: (v?: string) => v || '-',
        },
        {
            title: 'Debit',
            dataIndex: 'debit',
            key: 'debit',
            align: 'right' as const,
            render: moneyCell,
        },
        {
            title: 'Kredit',
            dataIndex: 'credit',
            key: 'credit',
            align: 'right' as const,
            render: moneyCell,
        },
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
            <Head title="Laporan Buku Besar" />

            <Card
                title="Laporan Buku Besar"
                extra={
                    <Space wrap>
                        <Select
                            showSearch
                            style={{ minWidth: 280 }}
                            value={accountId}
                            optionFilterProp="label"
                            onChange={onAccountChange}
                            options={accounts.map((a) => ({
                                value: a.id,
                                label: `${a.code} - ${a.name}`,
                            }))}
                        />
                        <DatePicker.RangePicker
                            value={[dayjs(start), dayjs(end)]}
                            onChange={onRange}
                        />
                        <Button
                            icon={<DownloadOutlined />}
                            onClick={() =>
                                window.open(
                                    route('reports.export.general-ledger', {
                                        account_id: accountId,
                                        start,
                                        end,
                                    }),
                                )
                            }
                        >
                            Export Excel
                        </Button>
                    </Space>
                }
            >
                {selectedAccount && (
                    <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
                        Akun: {selectedAccount.code} - {selectedAccount.name}
                    </Typography.Text>
                )}

                <Statistic
                    title="Saldo Akhir"
                    value={data.ending_balance}
                    formatter={(v) => formatIDR(Number(v))}
                    style={{ marginBottom: 16 }}
                />

                <Table
                    rowKey={(r) => `${r.journal_no}-${r.date}-${r.balance}`}
                    columns={columns}
                    dataSource={data.entries}
                    pagination={false}
                    size="small"
                />
            </Card>
        </AppLayout>
    );
}
