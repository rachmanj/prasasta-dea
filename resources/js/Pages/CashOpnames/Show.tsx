import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { FilePdfOutlined, RollbackOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Card, Col, Descriptions, InputNumber, Modal, Row, Space, Tag, Typography } from 'antd';
import dayjs from 'dayjs';

interface Line {
    id: number;
    denomination: number;
    type: 'banknote' | 'coin';
    units: number;
    amount: number | string;
}

interface StatusBadge {
    label: string;
    color: string;
}

interface Opname {
    id: number;
    number: string;
    date: string;
    book_balance: number | string;
    physical_balance: number | string;
    difference: number | string;
    status: string;
    status_badge: StatusBadge;
    notes?: string | null;
    lines: Line[];
    prepared_by?: { name: string } | null;
    adjustment_transaction?: { journal_no: string } | null;
}

interface Props {
    opname: Opname;
    terbilang: string;
    canAdjust: boolean;
}

export default function CashOpnameShow({ opname, terbilang, canAdjust }: Props) {
    const banknotes = opname.lines.filter((l) => l.type === 'banknote');
    const coins = opname.lines.filter((l) => l.type === 'coin');
    const diff = Number(opname.difference);
    const canPostAdjust = canAdjust && diff !== 0 && opname.status === 'open';

    const handleAdjust = () => {
        Modal.confirm({
            title: 'Posting Penyesuaian',
            content: `Yakin posting jurnal penyesuaian selisih ${formatIDR(Math.abs(diff))}?`,
            okText: 'Posting',
            cancelText: 'Batal',
            onOk: () =>
                new Promise<void>((resolve) => {
                    router.post(route('cash-opnames.adjust', opname.id), {}, { onFinish: () => resolve() });
                }),
        });
    };

    const renderLines = (lines: Line[], unitLabel: string) => (
        <Row gutter={[12, 8]}>
            {lines.map((line) => (
                <Col xs={24} sm={12} md={8} key={line.id}>
                    <div>
                        <Typography.Text type="secondary">
                            Rp {line.denomination.toLocaleString('id-ID')} ({unitLabel})
                        </Typography.Text>
                        <InputNumber
                            disabled
                            style={{ width: '100%', marginTop: 4 }}
                            value={line.units}
                            addonAfter={formatIDR(line.amount)}
                        />
                    </div>
                </Col>
            ))}
        </Row>
    );

    return (
        <AppLayout>
            <Head title={`Kas Opname ${opname.number}`} />

            <Space style={{ marginBottom: 16 }} wrap>
                <Button icon={<RollbackOutlined />} onClick={() => router.get(route('cash-opnames.index'))}>
                    Kembali
                </Button>
                <Button
                    icon={<FilePdfOutlined />}
                    onClick={() => window.open(route('cash-opnames.pdf', opname.id), '_blank')}
                >
                    Cetak PDF
                </Button>
                {canPostAdjust && (
                    <Button type="primary" danger onClick={handleAdjust}>
                        Posting Penyesuaian
                    </Button>
                )}
            </Space>

            <Card title={`Kas Opname — ${opname.number}`}>
                <Descriptions column={{ xs: 1, sm: 2 }} style={{ marginBottom: 24 }}>
                    <Descriptions.Item label="Tanggal">
                        {dayjs(opname.date).format('DD-MMM-YYYY')}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag color={opname.status_badge?.color}>{opname.status_badge?.label}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Saldo Buku">{formatIDR(opname.book_balance)}</Descriptions.Item>
                    <Descriptions.Item label="Saldo Fisik">{formatIDR(opname.physical_balance)}</Descriptions.Item>
                    <Descriptions.Item label="Selisih">{formatIDR(opname.difference)}</Descriptions.Item>
                    <Descriptions.Item label="Disiapkan oleh">
                        {opname.prepared_by?.name ?? '-'}
                    </Descriptions.Item>
                    {opname.adjustment_transaction && (
                        <Descriptions.Item label="Jurnal Penyesuaian">
                            {opname.adjustment_transaction.journal_no}
                        </Descriptions.Item>
                    )}
                    {opname.notes && (
                        <Descriptions.Item label="Catatan" span={2}>
                            {opname.notes}
                        </Descriptions.Item>
                    )}
                </Descriptions>

                <Typography.Title level={5}>Uang Kertas</Typography.Title>
                {renderLines(banknotes, 'lembar')}

                <Typography.Title level={5} style={{ marginTop: 16 }}>
                    Uang Logam
                </Typography.Title>
                {renderLines(coins, 'keping')}

                <Card size="small" style={{ marginTop: 24 }}>
                    <Typography.Text type="secondary">Terbilang: </Typography.Text>
                    <Typography.Text italic>{terbilang}</Typography.Text>
                </Card>
            </Card>
        </AppLayout>
    );
}
