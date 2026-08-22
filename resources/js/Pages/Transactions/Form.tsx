import AppLayout from '@/Components/AppLayout';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    DatePicker,
    Form,
    Input,
    InputNumber,
    Select,
    Space,
    Typography,
} from 'antd';
import dayjs from 'dayjs';

const TYPE_LABELS: Record<string, string> = {
    receipt: 'Uang Masuk',
    payment: 'Uang Keluar',
    transfer: 'Transfer',
    journal: 'Jurnal Umum',
};

interface Account {
    id: number;
    code: string;
    name: string;
    type?: string;
    is_bank?: boolean;
}

interface Line {
    account_id?: number;
    debit: number;
    credit: number;
}

interface Props {
    type: string;
    transaction: any;
    cashAccounts: Account[];
    revenueAccounts: Account[];
    expenseAccounts: Account[];
    allAccounts: Account[];
    programs: { id: number; code: string; name: string }[];
}

const accountOptions = (list: Account[]) =>
    list.map((a) => ({ value: a.id, label: `${a.code} - ${a.name}` }));

const currencyFormatter = (v?: number | string) =>
    `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) =>
    Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function TransactionForm({
    type,
    transaction,
    cashAccounts,
    revenueAccounts,
    expenseAccounts,
    allAccounts,
    programs,
}: Props) {
    const isEdit = !!transaction;

    const buildInitial = (): Record<string, any> => {
        if (!transaction) {
            return {
                type,
                date: dayjs().format('YYYY-MM-DD'),
                description: '',
                ref_no: '',
                program_id: null as number | null,
                amount: 0,
                lines: [
                    { account_id: undefined, debit: 0, credit: 0 },
                    { account_id: undefined, debit: 0, credit: 0 },
                ],
            };
        }

        const entries = transaction.journal_entries || [];
        const debit = entries.find((e: any) => Number(e.debit) > 0);
        const credit = entries.find((e: any) => Number(e.credit) > 0);
        const amount = Number(debit?.debit ?? 0);

        const base = {
            type,
            date: transaction.date,
            description: transaction.description ?? '',
            ref_no: transaction.ref_no ?? '',
            program_id: transaction.program_id ?? null,
            amount,
        };

        if (type === 'transfer') {
            return {
                ...base,
                from_account_id: credit?.account_id,
                to_account_id: debit?.account_id,
                lines: [],
            };
        }
        if (type === 'journal') {
            return {
                ...base,
                lines: entries.map((e: any) => ({
                    account_id: e.account_id,
                    debit: Number(e.debit),
                    credit: Number(e.credit),
                })),
            };
        }

        const cashLine = type === 'receipt' ? debit : credit;
        const otherLine = type === 'receipt' ? credit : debit;

        return {
            ...base,
            account_id: cashLine?.account_id,
            category_id: otherLine?.account_id,
            lines: [],
        };
    };

    const { data, setData, post, patch, processing, errors } = useForm(buildInitial());

    const submit = () => {
        if (isEdit) {
            patch(route('transactions.update', transaction.id));
        } else {
            post(route('transactions.store'));
        }
    };

    const addLine = () => {
        setData('lines', [
            ...data.lines,
            { account_id: undefined, debit: 0, credit: 0 },
        ]);
    };

    const removeLine = (idx: number) => {
        setData('lines', data.lines.filter((_: Line, i: number) => i !== idx));
    };

    const setLine = (idx: number, field: keyof Line, value: any) => {
        const lines = [...data.lines];
        lines[idx] = { ...lines[idx], [field]: value };
        setData('lines', lines);
    };

    return (
        <AppLayout>
            <Head title={TYPE_LABELS[type]} />

            <Card
                title={
                    <Space>
                        <span>
                            {isEdit ? 'Edit' : 'Tambah'} {TYPE_LABELS[type]}
                        </span>
                        {isEdit && <Typography.Text type="secondary">{transaction.journal_no}</Typography.Text>}
                    </Space>
                }
                style={{ maxWidth: 720 }}
                extra={
                    <Button onClick={() => history.back()}>Kembali</Button>
                }
            >
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Tanggal"
                        required
                        validateStatus={errors.date ? 'error' : undefined}
                        help={errors.date}
                    >
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.date ? dayjs(data.date) : null}
                            onChange={(_, ds) => setData('date', ds ?? '')}
                        />
                    </Form.Item>

                    {(type === 'receipt' || type === 'payment') && (
                        <>
                            <Form.Item
                                label="Rekening Kas / Bank"
                                required
                                validateStatus={errors.account_id ? 'error' : undefined}
                                help={errors.account_id}
                            >
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    value={data.account_id}
                                    onChange={(v) => setData('account_id', v)}
                                    options={accountOptions(cashAccounts)}
                                    placeholder="Pilih rekening"
                                />
                            </Form.Item>
                            <Form.Item
                                label={type === 'receipt' ? 'Kategori Pemasukan' : 'Kategori Pengeluaran'}
                                required
                                validateStatus={errors.category_id ? 'error' : undefined}
                                help={errors.category_id}
                            >
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    value={data.category_id}
                                    onChange={(v) => setData('category_id', v)}
                                    options={accountOptions(allAccounts)}
                                    placeholder="Pilih kategori"
                                />
                            </Form.Item>
                            <Form.Item
                                label="Nominal"
                                required
                                validateStatus={errors.amount ? 'error' : undefined}
                                help={errors.amount}
                            >
                                <InputNumber
                                    style={{ width: '100%' }}
                                    min={0}
                                    value={data.amount || null}
                                    onChange={(v) => setData('amount', v ?? 0)}
                                    formatter={currencyFormatter}
                                    parser={currencyParser}
                                />
                            </Form.Item>
                        </>
                    )}

                    {type === 'transfer' && (
                        <>
                            <Form.Item
                                label="Dari Rekening"
                                required
                                validateStatus={errors.from_account_id ? 'error' : undefined}
                                help={errors.from_account_id}
                            >
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    value={data.from_account_id}
                                    onChange={(v) => setData('from_account_id', v)}
                                    options={accountOptions(cashAccounts)}
                                    placeholder="Pilih rekening asal"
                                />
                            </Form.Item>
                            <Form.Item
                                label="Ke Rekening"
                                required
                                validateStatus={errors.to_account_id ? 'error' : undefined}
                                help={errors.to_account_id}
                            >
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    value={data.to_account_id}
                                    onChange={(v) => setData('to_account_id', v)}
                                    options={accountOptions(cashAccounts)}
                                    placeholder="Pilih rekening tujuan"
                                />
                            </Form.Item>
                            <Form.Item
                                label="Nominal"
                                required
                                validateStatus={errors.amount ? 'error' : undefined}
                                help={errors.amount}
                            >
                                <InputNumber
                                    style={{ width: '100%' }}
                                    min={0}
                                    value={data.amount || null}
                                    onChange={(v) => setData('amount', v ?? 0)}
                                    formatter={currencyFormatter}
                                    parser={currencyParser}
                                />
                            </Form.Item>
                        </>
                    )}

                    {type === 'journal' && (
                        <>
                            <Form.Item
                                label="Baris Jurnal"
                                required
                                validateStatus={errors.lines ? 'error' : undefined}
                                help={errors.lines}
                            >
                                <Space direction="vertical" style={{ width: '100%' }}>
                                    {data.lines.map((line: Line, i: number) => (
                                        <Space key={i} align="start">
                                            <Select
                                                style={{ width: 260 }}
                                                showSearch
                                                optionFilterProp="label"
                                                value={line.account_id}
                                                onChange={(v) => setLine(i, 'account_id', v)}
                                                options={accountOptions(allAccounts)}
                                                placeholder="Akun"
                                            />
                                            <InputNumber
                                                style={{ width: 140 }}
                                                min={0}
                                                value={line.debit || null}
                                                onChange={(v) => setLine(i, 'debit', v ?? 0)}
                                                formatter={currencyFormatter}
                                                parser={currencyParser}
                                                placeholder="Debit"
                                            />
                                            <InputNumber
                                                style={{ width: 140 }}
                                                min={0}
                                                value={line.credit || null}
                                                onChange={(v) => setLine(i, 'credit', v ?? 0)}
                                                formatter={currencyFormatter}
                                                parser={currencyParser}
                                                placeholder="Kredit"
                                            />
                                            {data.lines.length > 2 && (
                                                <Button
                                                    type="text"
                                                    danger
                                                    icon={<MinusCircleOutlined />}
                                                    onClick={() => removeLine(i)}
                                                />
                                            )}
                                        </Space>
                                    ))}
                                    <Button type="dashed" icon={<PlusOutlined />} onClick={addLine}>
                                        Tambah Baris
                                    </Button>
                                </Space>
                            </Form.Item>
                        </>
                    )}

                    <Form.Item label="Keterangan">
                        <Input
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Keterangan transaksi"
                        />
                    </Form.Item>

                    <Form.Item label="No. Referensi">
                        <Input
                            value={data.ref_no}
                            onChange={(e) => setData('ref_no', e.target.value)}
                            placeholder="Nomor referensi eksternal (opsional)"
                        />
                    </Form.Item>

                    {(type === 'receipt' || type === 'payment') && (
                        <Form.Item
                            label="Program"
                            validateStatus={errors.program_id ? 'error' : undefined}
                            help={errors.program_id}
                        >
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                value={data.program_id}
                                onChange={(v) => setData('program_id', v ?? null)}
                                options={programs.map((p) => ({
                                    value: p.id,
                                    label: `${p.code} - ${p.name}`,
                                }))}
                                placeholder="Pilih program (opsional)"
                            />
                        </Form.Item>
                    )}

                    <Form.Item>
                        <Space>
                            <Button type="primary" htmlType="submit" loading={processing}>
                                Simpan
                            </Button>
                            <Button onClick={() => history.back()}>Batal</Button>
                        </Space>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
