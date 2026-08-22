import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import {
    Alert,
    Button,
    Card,
    Col,
    DatePicker,
    Form,
    Input,
    InputNumber,
    Row,
    Select,
    Space,
    Statistic,
    Typography,
    theme,
} from 'antd';
import dayjs from 'dayjs';

interface Account {
    id: number;
    code: string;
    name: string;
}

interface Program {
    id: number;
    name: string;
}

interface ExpenseLine {
    account_id: number | undefined;
    amount: number;
    description: string;
}

interface Props {
    advance: any;
    expenseAccounts: Account[];
    programs: Program[];
}

const currencyFormatter = (v?: number | string) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) => Number((v ?? '').replace(/[^\d]/g, '')) || 0;

const accountOptions = (list: Account[]) =>
    list.map((a) => ({ value: a.id, label: `${a.code} - ${a.name}` }));

export default function CashAdvanceRealize({ advance, expenseAccounts, programs }: Props) {
    const { token } = theme.useToken();
    const remaining =
        Number(advance.amount) - Number(advance.realized_amount) - Number(advance.returned_amount);

    const { data, setData, post, processing, errors } = useForm({
        date: dayjs().format('YYYY-MM-DD'),
        program_id: advance.program_id ?? undefined,
        description: '',
        lines: [{ account_id: undefined, amount: 0, description: '' }] as ExpenseLine[],
        returned_amount: 0,
    });

    const expenseTotal = data.lines.reduce((sum, l) => sum + Number(l.amount || 0), 0);
    const total = expenseTotal + Number(data.returned_amount || 0);
    const overspend = total > remaining;

    const addLine = () => {
        setData('lines', [...data.lines, { account_id: undefined, amount: 0, description: '' }]);
    };

    const removeLine = (index: number) => {
        setData(
            'lines',
            data.lines.filter((_, i) => i !== index),
        );
    };

    const updateLine = (index: number, field: keyof ExpenseLine, value: unknown) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], [field]: value };
        setData('lines', lines);
    };

    const submit = () => {
        post(route('cash-advances.realize', advance.id));
    };

    return (
        <AppLayout>
            <Head title={`Realisasi ${advance.advance_no}`} />

            <Card title={`Realisasi Kas Bon ${advance.advance_no}`} style={{ maxWidth: 900 }}>
                <Row gutter={16} style={{ marginBottom: 24 }}>
                    <Col xs={24} sm={8}>
                        <Statistic title="Karyawan" value={advance.contact?.name ?? '-'} />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Sisa Kas Bon"
                            value={remaining}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{ color: token.colorWarning }}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Total Realisasi"
                            value={total}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{ color: overspend ? token.colorError : undefined }}
                        />
                    </Col>
                </Row>

                {overspend && (
                    <Alert
                        type="warning"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="Kurang bayar"
                        description={`Total melebihi sisa kas bon. Selisih ${formatIDR(total - remaining)} akan direimburse ke karyawan (Cr Kas).`}
                    />
                )}

                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Tanggal Realisasi"
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
                            onChange={(v) => setData('program_id', v)}
                            options={programs.map((p) => ({ value: p.id, label: p.name }))}
                            placeholder="Pilih program (opsional)"
                        />
                    </Form.Item>

                    <Form.Item label="Catatan">
                        <Input
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </Form.Item>

                    <Typography.Title level={5}>Baris Beban</Typography.Title>

                    {data.lines.map((line, index) => (
                        <Row key={index} gutter={8} align="middle" style={{ marginBottom: 8 }}>
                            <Col xs={24} sm={10}>
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    style={{ width: '100%' }}
                                    placeholder="Akun beban"
                                    value={line.account_id}
                                    onChange={(v) => updateLine(index, 'account_id', v)}
                                    options={accountOptions(expenseAccounts)}
                                />
                            </Col>
                            <Col xs={24} sm={6}>
                                <InputNumber
                                    style={{ width: '100%' }}
                                    min={0}
                                    value={line.amount || null}
                                    onChange={(v) => updateLine(index, 'amount', v ?? 0)}
                                    formatter={currencyFormatter}
                                    parser={currencyParser}
                                    placeholder="Nominal"
                                />
                            </Col>
                            <Col xs={24} sm={6}>
                                <Input
                                    value={line.description}
                                    onChange={(e) => updateLine(index, 'description', e.target.value)}
                                    placeholder="Keterangan"
                                />
                            </Col>
                            <Col xs={24} sm={2}>
                                {data.lines.length > 1 && (
                                    <Button
                                        type="text"
                                        danger
                                        icon={<MinusCircleOutlined />}
                                        onClick={() => removeLine(index)}
                                    />
                                )}
                            </Col>
                        </Row>
                    ))}

                    <Button type="dashed" onClick={addLine} icon={<PlusOutlined />} style={{ marginBottom: 16 }}>
                        Tambah Baris
                    </Button>

                    <Form.Item
                        label="Kas Kembali (opsional)"
                        validateStatus={errors.returned_amount ? 'error' : undefined}
                        help={errors.returned_amount}
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.returned_amount || null}
                            onChange={(v) => setData('returned_amount', v ?? 0)}
                            formatter={currencyFormatter}
                            parser={currencyParser}
                        />
                    </Form.Item>

                    <Form.Item>
                        <Space>
                            <Button type="primary" htmlType="submit" loading={processing}>
                                Simpan Realisasi
                            </Button>
                            <Button onClick={() => history.back()}>Batal</Button>
                        </Space>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
