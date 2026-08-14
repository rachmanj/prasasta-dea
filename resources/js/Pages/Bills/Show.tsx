import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    Col,
    DatePicker,
    Descriptions,
    Form,
    InputNumber,
    Modal,
    Popconfirm,
    Row,
    Select,
    Space,
    Statistic,
    Table,
    Tag,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';

const STATUS_LABELS: Record<string, string> = {
    open: 'Belum Lunas',
    partial: 'Sebagian',
    paid: 'Lunas',
};
const STATUS_COLORS: Record<string, string> = {
    open: 'red',
    partial: 'orange',
    paid: 'green',
};

interface Account {
    id: number;
    code: string;
    name: string;
}

export default function BillShow({ bill, cashAccounts }: { bill: any; cashAccounts: Account[] }) {
    const [open, setOpen] = useState(false);
    const isReceivable = bill.type === 'receivable';
    const remaining = Number(bill.amount) - Number(bill.paid_amount);

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: 0,
        account_id: undefined as number | undefined,
        date: dayjs().format('YYYY-MM-DD'),
    });

    const openModal = () => {
        reset();
        setData('amount', remaining);
        setOpen(true);
    };

    const submitPayment = () => {
        post(route('bills.payments.store', bill.id), {
            onSuccess: () => setOpen(false),
        });
    };

    const paymentColumns = [
        { title: 'Tanggal', dataIndex: 'date', key: 'date' },
        {
            title: 'Nominal',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
        {
            title: 'No. Jurnal',
            key: 'journal',
            render: (_: unknown, r: any) => r.transaction?.journal_no ?? '-',
        },
    ];

    return (
        <AppLayout>
            <Head title={bill.bill_no} />

            <Row gutter={[16, 16]}>
                <Col span={24}>
                    <Card
                        title={`${isReceivable ? 'Piutang' : 'Hutang'} ${bill.bill_no}`}
                        extra={
                            <Space>
                                {remaining > 0 && (
                                    <Button type="primary" onClick={openModal}>
                                        Catat Pembayaran
                                    </Button>
                                )}
                                <Popconfirm
                                    title="Hapus tagihan ini?"
                                    onConfirm={() => router.delete(route('bills.destroy', bill.id))}
                                >
                                    <Button danger>Hapus</Button>
                                </Popconfirm>
                            </Space>
                        }
                    >
                        <Descriptions column={2} bordered size="small">
                            <Descriptions.Item label="Kontak">{bill.contact?.name}</Descriptions.Item>
                            <Descriptions.Item label="Status">
                                <Tag color={STATUS_COLORS[bill.status]}>{STATUS_LABELS[bill.status]}</Tag>
                            </Descriptions.Item>
                            <Descriptions.Item label="Tanggal">{bill.date}</Descriptions.Item>
                            <Descriptions.Item label="Jatuh Tempo">{bill.due_date}</Descriptions.Item>
                            <Descriptions.Item label="Akun">{bill.account ? `${bill.account.code} - ${bill.account.name}` : '-'}</Descriptions.Item>
                            <Descriptions.Item label="Keterangan">{bill.description || '-'}</Descriptions.Item>
                        </Descriptions>

                        <Row gutter={16} style={{ marginTop: 16 }}>
                            <Col xs={24} sm={8}>
                                <Statistic title="Total Tagihan" value={Number(bill.amount)} formatter={(v) => formatIDR(Number(v))} />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic title="Sudah Dibayar" value={Number(bill.paid_amount)} formatter={(v) => formatIDR(Number(v))} />
                            </Col>
                            <Col xs={24} sm={8}>
                                <Statistic title="Sisa" value={remaining} formatter={(v) => formatIDR(Number(v))} valueStyle={{ color: remaining > 0 ? '#faad14' : '#52c41a' }} />
                            </Col>
                        </Row>
                    </Card>
                </Col>
            </Row>

            <Card title="Riwayat Pembayaran" style={{ marginTop: 16 }}>
                <Table rowKey="id" columns={paymentColumns} dataSource={bill.payments} pagination={false} size="small" />
            </Card>

            <Modal
                title="Catat Pembayaran"
                open={open}
                onCancel={() => setOpen(false)}
                onOk={submitPayment}
                confirmLoading={processing}
                destroyOnClose
            >
                <Form layout="vertical">
                    <Form.Item label="Tanggal">
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.date ? dayjs(data.date) : null}
                            onChange={(_, ds) => setData('date', ds ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Rekening Kas / Bank" validateStatus={errors.account_id ? 'error' : undefined} help={errors.account_id}>
                        <Select
                            value={data.account_id}
                            onChange={(v) => setData('account_id', v)}
                            options={cashAccounts.map((a) => ({ value: a.id, label: `${a.code} - ${a.name}` }))}
                            placeholder="Pilih rekening"
                        />
                    </Form.Item>
                    <Form.Item label="Nominal" validateStatus={errors.amount ? 'error' : undefined} help={errors.amount}>
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            max={remaining}
                            value={data.amount || null}
                            onChange={(v) => setData('amount', v ?? 0)}
                            formatter={(v) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`}
                            parser={(v) => Number((v ?? '').replace(/[^\d]/g, '')) || 0}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
