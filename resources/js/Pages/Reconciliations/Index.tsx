import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { UploadOutlined } from '@ant-design/icons';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    Col,
    DatePicker,
    Form,
    InputNumber,
    Modal,
    Row,
    Select,
    Space,
    Statistic,
    Table,
    Tag,
    Upload,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';

interface BankAccount {
    id: number;
    code: string;
    name: string;
    bank_name?: string;
    account_number?: string;
}

interface Reconciliation {
    id: number;
    as_of_date: string;
    closing_balance: number | string;
    status: string;
}

interface Props {
    bankAccounts: BankAccount[];
    selectedAccount: BankAccount | null;
    lines: any[];
    candidates: any[];
    reconciliations: Reconciliation[];
    balances: { id: number; code: string; name: string; balance: number }[];
}

const currencyFormatter = (v?: number | string) =>
    `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) =>
    Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function ReconciliationsIndex({
    bankAccounts,
    selectedAccount,
    lines,
    candidates,
    reconciliations,
    balances,
}: Props) {
    const [importOpen, setImportOpen] = useState(false);
    const selectedBalance = selectedAccount
        ? balances.find((b) => b.id === selectedAccount.id)
        : null;

    const latestReconciliation = reconciliations[0] ?? null;
    const selisih =
        selectedBalance && latestReconciliation
            ? selectedBalance.balance - Number(latestReconciliation.closing_balance)
            : null;

    const importForm = useForm({
        account_id: selectedAccount?.id as number | undefined,
        file: null as File | null,
    });

    const completeForm = useForm({
        account_id: selectedAccount?.id as number | undefined,
        as_of_date: dayjs().format('YYYY-MM-DD'),
        closing_balance: 0,
    });

    const txAmount = (tx: any) =>
        (tx.journal_entries || []).reduce((s: number, e: any) => s + Number(e.debit), 0);

    const candidateOptions = candidates.map((c) => ({
        value: c.id,
        label: `${c.journal_no} · ${c.date} · ${formatIDR(txAmount(c))}`,
    }));

    const columns = [
        { title: 'Tanggal', dataIndex: 'date', key: 'date' },
        { title: 'Keterangan', dataIndex: 'description', key: 'description', render: (v?: string) => v || '-' },
        {
            title: 'Nominal',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right' as const,
            render: (v: number) => (
                <span style={{ color: v >= 0 ? '#52c41a' : '#ff4d4f' }}>
                    {v >= 0 ? '+' : ''}
                    {formatIDR(v)}
                </span>
            ),
        },
        {
            title: 'Status',
            key: 'status',
            render: (_: unknown, r: any) =>
                r.is_matched ? <Tag color="green">Cocok</Tag> : <Tag color="orange">Belum</Tag>,
        },
        {
            title: 'Cocokkan Dengan',
            key: 'match',
            width: 320,
            render: (_: unknown, r: any) => {
                if (r.is_matched) {
                    return (
                        <Space>
                            <span>{r.transaction?.journal_no ?? '-'}</span>
                            <Button
                                size="small"
                                onClick={() =>
                                    router.post(route('reconciliations.unmatch', r.id))
                                }
                            >
                                Batal
                            </Button>
                        </Space>
                    );
                }
                return (
                    <Select
                        size="small"
                        style={{ width: 280 }}
                        placeholder="Pilih transaksi..."
                        showSearch
                        optionFilterProp="label"
                        options={candidateOptions}
                        onChange={(v) =>
                            router.post(route('reconciliations.match', r.id), {
                                transaction_id: v,
                            })
                        }
                    />
                );
            },
        },
    ];

    const importSubmit = () => {
        importForm.post(route('reconciliations.import'), {
            onSuccess: () => setImportOpen(false),
        });
    };

    const completeSubmit = () => {
        completeForm.post(route('reconciliations.store'));
    };

    const historyColumns = [
        {
            title: 'Per Tanggal',
            dataIndex: 'as_of_date',
            key: 'as_of_date',
        },
        {
            title: 'Saldo Penutup',
            dataIndex: 'closing_balance',
            key: 'closing_balance',
            align: 'right' as const,
            render: (v: number | string) => formatIDR(v),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (v: string) =>
                v === 'completed' ? (
                    <Tag color="green">Selesai</Tag>
                ) : (
                    <Tag color="orange">Terbuka</Tag>
                ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Rekonsiliasi Bank" />

            <Card
                title="Rekonsiliasi Bank"
                extra={
                    <Space>
                        <Select
                            style={{ width: 240 }}
                            placeholder="Pilih rekening"
                            value={selectedAccount?.id}
                            onChange={(v) =>
                                router.get(route('reconciliations.index'), { account_id: v })
                            }
                            options={bankAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.code} - ${a.name}`,
                            }))}
                        />
                        {selectedAccount && (
                            <Button
                                type="primary"
                                icon={<UploadOutlined />}
                                onClick={() => setImportOpen(true)}
                            >
                                Import CSV
                            </Button>
                        )}
                    </Space>
                }
            >
                {selectedAccount ? (
                    <>
                        <Row gutter={16} style={{ marginBottom: 16 }}>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Saldo Buku (GL)"
                                    value={selectedBalance?.balance ?? 0}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Total Statement Import"
                                    value={lines.reduce((s: number, l: any) => s + Number(l.amount), 0)}
                                    formatter={(v) => formatIDR(Number(v))}
                                />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic
                                    title="Belum Cocok"
                                    value={lines.filter((l: any) => !l.is_matched).length}
                                />
                            </Col>
                            {selisih !== null && (
                                <Col xs={24} sm={8}>
                                    <Statistic
                                        title="Selisih"
                                        value={selisih}
                                        formatter={(v) => formatIDR(Number(v))}
                                        valueStyle={{
                                            color: Number(selisih) === 0 ? '#52c41a' : '#faad14',
                                        }}
                                    />
                                </Col>
                            )}
                        </Row>

                        <Table
                            rowKey="id"
                            columns={columns}
                            dataSource={lines}
                            pagination={false}
                            size="small"
                            scroll={{ y: 420 }}
                        />
                    </>
                ) : (
                    <p>Pilih rekening bank untuk mulai rekonsiliasi.</p>
                )}
            </Card>

            {selectedAccount && (
                <>
                    <Card title="Selesaikan Rekonsiliasi" style={{ marginTop: 16 }}>
                        <Form layout="inline" onFinish={completeSubmit}>
                            <Form.Item label="Per Tanggal">
                                <DatePicker
                                    value={dayjs(completeForm.data.as_of_date)}
                                    onChange={(d) =>
                                        completeForm.setData(
                                            'as_of_date',
                                            d ? d.format('YYYY-MM-DD') : '',
                                        )
                                    }
                                    format="DD/MM/YYYY"
                                />
                            </Form.Item>
                            <Form.Item label="Saldo Penutup">
                                <InputNumber
                                    min={0}
                                    formatter={currencyFormatter}
                                    parser={currencyParser}
                                    value={completeForm.data.closing_balance}
                                    onChange={(v) =>
                                        completeForm.setData(
                                            'closing_balance',
                                            Number(v) || 0,
                                        )
                                    }
                                    style={{ width: 200 }}
                                />
                            </Form.Item>
                            <Form.Item>
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    loading={completeForm.processing}
                                >
                                    Selesaikan
                                </Button>
                            </Form.Item>
                        </Form>
                    </Card>

                    <Card title="Riwayat Rekonsiliasi" style={{ marginTop: 16 }}>
                        <Table
                            rowKey="id"
                            columns={historyColumns}
                            dataSource={reconciliations}
                            pagination={false}
                            size="small"
                        />
                    </Card>
                </>
            )}

            <Modal
                title="Import Statement Bank"
                open={importOpen}
                onCancel={() => setImportOpen(false)}
                onOk={importSubmit}
                confirmLoading={importForm.processing}
                destroyOnClose
            >
                <Space direction="vertical" style={{ width: '100%' }}>
                    <Upload
                        beforeUpload={(file) => {
                            importForm.setData('file', file);
                            return false;
                        }}
                        maxCount={1}
                        accept=".csv,.txt"
                    >
                        <Button icon={<UploadOutlined />}>Pilih File CSV</Button>
                    </Upload>
                    <p style={{ color: '#888' }}>
                        Format: CSV bank (BCA/BRI/Mandiri). Kolom tanggal, keterangan, dan
                        debit/kredit atau nominal akan dikenali otomatis.
                    </p>
                </Space>
            </Modal>
        </AppLayout>
    );
}
