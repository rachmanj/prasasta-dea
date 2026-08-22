import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    Col,
    Descriptions,
    Form,
    Input,
    InputNumber,
    Modal,
    Popconfirm,
    Row,
    Space,
    Statistic,
    Table,
    Tag,
    theme,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';

interface Participant {
    id: number;
    name: string;
    nis: string | null;
    region: string | null;
    fee: number | string;
    paid_amount: number | string;
    payment_date: string | null;
    status: string;
}

interface ProfitLossLine {
    code: string;
    name: string;
    net: number;
}

interface ProfitLoss {
    revenue: number;
    expense: number;
    profit: number;
    revenue_lines: ProfitLossLine[];
    expense_lines: ProfitLossLine[];
}

interface Program {
    id: number;
    code: string;
    name: string;
    type: string;
    start_date: string | null;
    end_date: string | null;
    status: string;
    notes: string | null;
    participants: Participant[];
}

interface Props {
    program: Program;
    profitLoss: ProfitLoss;
    canManage?: boolean;
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

const PAYMENT_STATUS: Record<string, { label: string; color: string }> = {
    paid: { label: 'Lunas', color: 'green' },
    partial: { label: 'Sebagian', color: 'orange' },
    unpaid: { label: 'Belum Bayar', color: 'red' },
};

const currencyFormatter = (v?: number | string) =>
    `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) =>
    Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function ProgramShow({ program, profitLoss, canManage = false }: Props) {
    const { token } = theme.useToken();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Participant | null>(null);

    const { data, setData, post, patch, processing, errors, reset } = useForm({
        name: '',
        nis: '',
        region: '',
        fee: 0,
        paid_amount: 0,
        payment_date: '',
    });

    const openAdd = () => {
        reset();
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (p: Participant) => {
        setEditing(p);
        setData({
            name: p.name,
            nis: p.nis ?? '',
            region: p.region ?? '',
            fee: Number(p.fee),
            paid_amount: Number(p.paid_amount),
            payment_date: p.payment_date ?? '',
        });
        setModalOpen(true);
    };

    const submitParticipant = () => {
        if (editing) {
            patch(route('participants.update', editing.id), {
                onSuccess: () => {
                    setModalOpen(false);
                    reset();
                },
            });
        } else {
            post(route('programs.participants.store', program.id), {
                onSuccess: () => {
                    setModalOpen(false);
                    reset();
                },
            });
        }
    };

    const plLineColumns = [
        {
            title: 'Akun',
            key: 'account',
            render: (_: unknown, r: ProfitLossLine) => `${r.code} - ${r.name}`,
        },
        {
            title: 'Nominal',
            dataIndex: 'net',
            key: 'net',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
    ];

    const participantColumns = [
        { title: 'Nama', dataIndex: 'name', key: 'name' },
        { title: 'NIS', dataIndex: 'nis', key: 'nis', render: (v: string | null) => v || '-' },
        { title: 'Wilayah', dataIndex: 'region', key: 'region', render: (v: string | null) => v || '-' },
        {
            title: 'Biaya',
            dataIndex: 'fee',
            key: 'fee',
            align: 'right' as const,
            render: (v: number | string) => formatIDR(v),
        },
        {
            title: 'Dibayar',
            dataIndex: 'paid_amount',
            key: 'paid_amount',
            align: 'right' as const,
            render: (v: number | string) => formatIDR(v),
        },
        {
            title: 'Tgl Bayar',
            dataIndex: 'payment_date',
            key: 'payment_date',
            render: (v: string | null) => (v ? dayjs(v).format('DD-MMM-YYYY') : '-'),
        },
        {
            title: 'Status Bayar',
            key: 'status',
            render: (_: unknown, r: Participant) => {
                const s = PAYMENT_STATUS[r.status] ?? { label: r.status, color: 'default' };
                return <Tag color={s.color}>{s.label}</Tag>;
            },
        },
        ...(canManage
            ? [
                  {
                      title: 'Aksi',
                      key: 'aksi',
                      width: 140,
                      render: (_: unknown, r: Participant) => (
                          <Space>
                              <Button size="small" onClick={() => openEdit(r)}>
                                  Edit
                              </Button>
                              <Popconfirm
                                  title="Hapus peserta ini?"
                                  onConfirm={() =>
                                      router.delete(route('participants.destroy', r.id))
                                  }
                              >
                                  <Button size="small" danger>
                                      Hapus
                                  </Button>
                              </Popconfirm>
                          </Space>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={program.name} />

            <Card
                title={`${program.code} — ${program.name}`}
                extra={
                    canManage ? (
                        <Space>
                            <Button
                                icon={<EditOutlined />}
                                onClick={() => router.get(route('programs.edit', program.id))}
                            >
                                Edit
                            </Button>
                            <Popconfirm
                                title="Hapus program ini?"
                                onConfirm={() => router.delete(route('programs.destroy', program.id))}
                            >
                                <Button danger>Hapus</Button>
                            </Popconfirm>
                        </Space>
                    ) : null
                }
            >
                <Descriptions column={2} bordered size="small">
                    <Descriptions.Item label="Jenis">
                        {TYPE_LABELS[program.type] ?? program.type}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        {STATUS_LABELS[program.status] ?? program.status}
                    </Descriptions.Item>
                    <Descriptions.Item label="Tanggal Mulai">
                        {program.start_date
                            ? dayjs(program.start_date).format('DD-MMM-YYYY')
                            : '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Tanggal Selesai">
                        {program.end_date
                            ? dayjs(program.end_date).format('DD-MMM-YYYY')
                            : '-'}
                    </Descriptions.Item>
                    {program.notes && (
                        <Descriptions.Item label="Catatan" span={2}>
                            {program.notes}
                        </Descriptions.Item>
                    )}
                </Descriptions>
            </Card>

            <Card title="Laba Rugi Program" style={{ marginTop: 16 }}>
                <Row gutter={16}>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Pendapatan"
                            value={profitLoss.revenue}
                            valueStyle={{ color: token.colorSuccess }}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Biaya"
                            value={profitLoss.expense}
                            valueStyle={{ color: token.colorError }}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <Statistic
                            title="Laba"
                            value={profitLoss.profit}
                            valueStyle={{
                                color:
                                    profitLoss.profit >= 0
                                        ? token.colorSuccess
                                        : token.colorError,
                            }}
                            formatter={(v) => formatIDR(Number(v))}
                        />
                    </Col>
                </Row>
            </Card>

            <Row gutter={16} style={{ marginTop: 16 }}>
                <Col xs={24} lg={12}>
                    <Card title="Rincian Pendapatan" size="small">
                        <Table
                            rowKey={(r) => r.code}
                            size="small"
                            columns={plLineColumns}
                            dataSource={profitLoss.revenue_lines}
                            pagination={false}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Rincian Biaya" size="small">
                        <Table
                            rowKey={(r) => r.code}
                            size="small"
                            columns={plLineColumns}
                            dataSource={profitLoss.expense_lines}
                            pagination={false}
                        />
                    </Card>
                </Col>
            </Row>

            <Card
                title="Peserta"
                style={{ marginTop: 16 }}
                extra={
                    canManage ? (
                        <Button type="primary" icon={<PlusOutlined />} onClick={openAdd}>
                            Tambah Peserta
                        </Button>
                    ) : null
                }
            >
                <Table
                    rowKey="id"
                    size="small"
                    columns={participantColumns}
                    dataSource={program.participants}
                    pagination={false}
                />
            </Card>

            <Modal
                title={editing ? 'Edit Peserta' : 'Tambah Peserta'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submitParticipant}
                confirmLoading={processing}
                okText="Simpan"
                cancelText="Batal"
            >
                <Form layout="vertical">
                    <Form.Item
                        label="Nama"
                        required
                        validateStatus={errors.name ? 'error' : undefined}
                        help={errors.name}
                    >
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="NIS">
                        <Input
                            value={data.nis}
                            onChange={(e) => setData('nis', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Wilayah">
                        <Input
                            value={data.region}
                            onChange={(e) => setData('region', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Biaya Kursus">
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.fee || null}
                            onChange={(v) => setData('fee', v ?? 0)}
                            formatter={currencyFormatter}
                            parser={currencyParser}
                        />
                    </Form.Item>
                    <Form.Item label="Jumlah Dibayar">
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.paid_amount || null}
                            onChange={(v) => setData('paid_amount', v ?? 0)}
                            formatter={currencyFormatter}
                            parser={currencyParser}
                        />
                    </Form.Item>
                    <Form.Item label="Tanggal Bayar">
                        <Input
                            type="date"
                            value={data.payment_date}
                            onChange={(e) => setData('payment_date', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
