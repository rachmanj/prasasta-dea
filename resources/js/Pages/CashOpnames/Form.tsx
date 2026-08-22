import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { terbilangRupiah } from '@/lib/terbilang';
import { Head, router, useForm } from '@inertiajs/react';
import { Button, Card, Col, DatePicker, Form, Input, InputNumber, Row, Space, Typography } from 'antd';
import dayjs from 'dayjs';
import { useMemo } from 'react';

interface Denomination {
    denomination: number;
    type: 'banknote' | 'coin';
}

interface LineInput {
    denomination: number;
    type: 'banknote' | 'coin';
    units: number;
}

interface Props {
    denominations: Denomination[];
    bookBalance: number;
    previewDate: string;
    canCreate?: boolean;
}

export default function CashOpnameForm({ denominations, bookBalance, previewDate }: Props) {
    const initialLines: LineInput[] = denominations.map((d) => ({
        denomination: d.denomination,
        type: d.type,
        units: 0,
    }));

    const { data, setData, post, processing, errors } = useForm({
        date: previewDate,
        notes: '',
        lines: initialLines,
    });

    const banknotes = denominations.filter((d) => d.type === 'banknote');
    const coins = denominations.filter((d) => d.type === 'coin');

    const physicalBalance = useMemo(() => {
        return data.lines.reduce(
            (sum, line) => sum + line.denomination * (line.units || 0),
            0,
        );
    }, [data.lines]);

    const difference = useMemo(
        () => Math.round((bookBalance - physicalBalance) * 100) / 100,
        [bookBalance, physicalBalance],
    );

    const updateUnits = (denomination: number, type: string, units: number) => {
        setData(
            'lines',
            data.lines.map((line) =>
                line.denomination === denomination && line.type === type
                    ? { ...line, units: units || 0 }
                    : line,
            ),
        );
    };

    const getUnits = (denomination: number, type: string) =>
        data.lines.find((l) => l.denomination === denomination && l.type === type)?.units ?? 0;

    const onDateChange = (_: unknown, dateString: string | string[] | null) => {
        const ds = (Array.isArray(dateString) ? dateString[0] : dateString) ?? '';
        setData('date', ds);
        router.get(route('cash-opnames.create'), { date: ds }, { preserveState: true, replace: true });
    };

    const submit = () => post(route('cash-opnames.store'));

    const renderDenomGrid = (items: Denomination[], unitLabel: string) => (
        <Row gutter={[12, 12]}>
            {items.map((d) => (
                <Col xs={24} sm={12} md={8} key={`${d.type}-${d.denomination}`}>
                    <Form.Item label={`Rp ${d.denomination.toLocaleString('id-ID')} (${unitLabel})`}>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={getUnits(d.denomination, d.type)}
                            onChange={(v) => updateUnits(d.denomination, d.type, Number(v) || 0)}
                        />
                    </Form.Item>
                </Col>
            ))}
        </Row>
    );

    return (
        <AppLayout>
            <Head title="Opname Baru" />

            <Card title="Kas Opname Baru" style={{ maxWidth: 960 }}>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Tanggal Opname"
                        required
                        validateStatus={errors.date ? 'error' : undefined}
                        help={errors.date}
                    >
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.date ? dayjs(data.date) : null}
                            onChange={onDateChange}
                            format="DD-MMM-YYYY"
                        />
                    </Form.Item>

                    <Card size="small" title="Saldo Buku (GL)" style={{ marginBottom: 16 }}>
                        <Typography.Text strong>{formatIDR(bookBalance)}</Typography.Text>
                        <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
                            Snapshot saldo akun Kas (1000) per tanggal opname
                        </Typography.Paragraph>
                    </Card>

                    <Typography.Title level={5}>Uang Kertas</Typography.Title>
                    {renderDenomGrid(banknotes, 'lembar')}

                    <Typography.Title level={5} style={{ marginTop: 16 }}>
                        Uang Logam
                    </Typography.Title>
                    {renderDenomGrid(coins, 'keping')}

                    <Card size="small" title="Ringkasan" style={{ marginTop: 16, marginBottom: 16 }}>
                        <Space direction="vertical" style={{ width: '100%' }}>
                            <div>
                                <Typography.Text type="secondary">Total Fisik: </Typography.Text>
                                <Typography.Text strong>{formatIDR(physicalBalance)}</Typography.Text>
                            </div>
                            <div>
                                <Typography.Text type="secondary">Selisih (Buku − Fisik): </Typography.Text>
                                <Typography.Text
                                    strong
                                    type={difference === 0 ? 'success' : 'warning'}
                                >
                                    {formatIDR(difference)}
                                </Typography.Text>
                            </div>
                            <div>
                                <Typography.Text type="secondary">Terbilang: </Typography.Text>
                                <Typography.Text italic>{terbilangRupiah(physicalBalance)}</Typography.Text>
                            </div>
                        </Space>
                    </Card>

                    <Form.Item label="Catatan" validateStatus={errors.notes ? 'error' : undefined} help={errors.notes}>
                        <Input.TextArea
                            rows={2}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </Form.Item>

                    <Space>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Simpan
                        </Button>
                        <Button onClick={() => router.get(route('cash-opnames.index'))}>Batal</Button>
                    </Space>
                </Form>
            </Card>
        </AppLayout>
    );
}
