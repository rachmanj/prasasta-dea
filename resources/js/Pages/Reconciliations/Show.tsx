import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import {
    Alert,
    Button,
    Card,
    Checkbox,
    Col,
    Descriptions,
    Popconfirm,
    Row,
    Space,
    Statistic,
    Table,
    Tag,
    theme,
} from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';

const BANK_STATUS_LABELS: Record<string, string> = {
    unmatched: 'Belum Cocok',
    matched: 'Cocok (Otomatis)',
    manual: 'Cocok (Manual)',
    excluded: 'Dikecualikan',
};

const BANK_STATUS_COLORS: Record<string, string> = {
    unmatched: 'orange',
    matched: 'green',
    manual: 'blue',
    excluded: 'default',
};

interface BankLine {
    id: number;
    transaction_date: string;
    description: string;
    reference?: string | null;
    debit: number | string;
    credit: number | string;
    balance?: number | string | null;
    matched_status: string;
}

interface BookLine {
    id: number;
    date: string;
    journal_no: string;
    description: string;
    debit: number;
    credit: number;
    net: number;
    matched_status: string;
}

interface MatchGroup {
    id: number;
    match_type: string;
    bank_total: number;
    book_total: number;
    difference: number;
    bank_line_ids: number[];
    transaction_ids: number[];
}

interface Balances {
    bank_net: number;
    book_net: number;
    difference: number;
    opening_balance_bank: number;
    closing_balance_bank: number;
    opening_balance_book: number;
    closing_balance_book: number;
    adjusted_bank: number;
    adjusted_book: number;
    unexplained: number;
    unmatched_bank_count: number;
    unmatched_book_count: number;
    is_balanced: boolean;
}

interface Reconciliation {
    id: number;
    period: string;
    status: string;
    notes?: string | null;
    account?: { code: string; name: string };
}

interface Props {
    reconciliation: Reconciliation;
    bankLines: BankLine[];
    bookLines: BookLine[];
    matchGroups: MatchGroup[];
    balances: Balances;
}

export default function ReconciliationsShow({
    reconciliation,
    bankLines,
    bookLines,
    matchGroups,
    balances,
}: Props) {
    const { token } = theme.useToken();
    const [selectedBankIds, setSelectedBankIds] = useState<number[]>([]);
    const [selectedBookIds, setSelectedBookIds] = useState<number[]>([]);
    const editable = reconciliation.status !== 'completed';

    const selectedBankNet = useMemo(
        () =>
            bankLines
                .filter((l) => selectedBankIds.includes(l.id))
                .reduce((s, l) => s + Number(l.debit) - Number(l.credit), 0),
        [bankLines, selectedBankIds],
    );

    const selectedBookNet = useMemo(
        () =>
            bookLines
                .filter((l) => selectedBookIds.includes(l.id))
                .reduce((s, l) => s + l.net, 0),
        [bookLines, selectedBookIds],
    );

    const selectionDifference = round2(selectedBankNet + selectedBookNet);
    const canManualMatch =
        editable &&
        selectedBankIds.length > 0 &&
        selectedBookIds.length > 0 &&
        Math.abs(selectionDifference) < 0.005;

    const toggleBank = (id: number, checked: boolean) => {
        const line = bankLines.find((l) => l.id === id);
        if (!line || line.matched_status !== 'unmatched') return;
        setSelectedBankIds((prev) =>
            checked ? [...prev, id] : prev.filter((x) => x !== id),
        );
    };

    const toggleBook = (id: number, checked: boolean) => {
        const line = bookLines.find((l) => l.id === id);
        if (!line || line.matched_status !== 'unmatched') return;
        setSelectedBookIds((prev) =>
            checked ? [...prev, id] : prev.filter((x) => x !== id),
        );
    };

    const manualMatch = () => {
        router.post(route('reconciliations.match', reconciliation.id), {
            bank_line_ids: selectedBankIds,
            transaction_ids: selectedBookIds,
        }, {
            onSuccess: () => {
                setSelectedBankIds([]);
                setSelectedBookIds([]);
            },
        });
    };

    const bankColumns = [
        {
            title: '',
            key: 'select',
            width: 40,
            render: (_: unknown, r: BankLine) =>
                r.matched_status === 'unmatched' && editable ? (
                    <Checkbox
                        checked={selectedBankIds.includes(r.id)}
                        onChange={(e) => toggleBank(r.id, e.target.checked)}
                    />
                ) : null,
        },
        {
            title: 'Tanggal',
            dataIndex: 'transaction_date',
            key: 'date',
            render: (v: string) => dayjs(v).format('DD-MMM-YYYY'),
        },
        {
            title: 'Keterangan',
            dataIndex: 'description',
            key: 'description',
            ellipsis: true,
        },
        {
            title: 'Ref',
            dataIndex: 'reference',
            key: 'reference',
            render: (v?: string | null) => v ?? '-',
        },
        {
            title: 'Debit',
            key: 'debit',
            align: 'right' as const,
            render: (_: unknown, r: BankLine) =>
                Number(r.debit) > 0 ? formatIDR(r.debit) : '-',
        },
        {
            title: 'Kredit',
            key: 'credit',
            align: 'right' as const,
            render: (_: unknown, r: BankLine) =>
                Number(r.credit) > 0 ? formatIDR(r.credit) : '-',
        },
        {
            title: 'Status',
            key: 'status',
            render: (_: unknown, r: BankLine) => (
                <Tag color={BANK_STATUS_COLORS[r.matched_status]}>
                    {BANK_STATUS_LABELS[r.matched_status] ?? r.matched_status}
                </Tag>
            ),
        },
        {
            title: '',
            key: 'actions',
            render: (_: unknown, r: BankLine) =>
                editable && r.matched_status === 'unmatched' ? (
                    <Button
                        size="small"
                        onClick={() =>
                            router.post(
                                route('reconciliations.exclude', [reconciliation.id, r.id]),
                            )
                        }
                    >
                        Kecualikan
                    </Button>
                ) : editable && r.matched_status === 'excluded' ? (
                    <Button
                        size="small"
                        onClick={() =>
                            router.post(
                                route('reconciliations.exclude', [reconciliation.id, r.id]),
                            )
                        }
                    >
                        Aktifkan
                    </Button>
                ) : null,
        },
    ];

    const bookColumns = [
        {
            title: '',
            key: 'select',
            width: 40,
            render: (_: unknown, r: BookLine) =>
                r.matched_status === 'unmatched' && editable ? (
                    <Checkbox
                        checked={selectedBookIds.includes(r.id)}
                        onChange={(e) => toggleBook(r.id, e.target.checked)}
                    />
                ) : null,
        },
        {
            title: 'Tanggal',
            dataIndex: 'date',
            key: 'date',
            render: (v: string) => dayjs(v).format('DD-MMM-YYYY'),
        },
        {
            title: 'No. Jurnal',
            dataIndex: 'journal_no',
            key: 'journal_no',
        },
        {
            title: 'Keterangan',
            dataIndex: 'description',
            key: 'description',
            ellipsis: true,
        },
        {
            title: 'Debit',
            key: 'debit',
            align: 'right' as const,
            render: (_: unknown, r: BookLine) =>
                r.debit > 0 ? formatIDR(r.debit) : '-',
        },
        {
            title: 'Kredit',
            key: 'credit',
            align: 'right' as const,
            render: (_: unknown, r: BookLine) =>
                r.credit > 0 ? formatIDR(r.credit) : '-',
        },
        {
            title: 'Status',
            key: 'status',
            render: (_: unknown, r: BookLine) =>
                r.matched_status === 'unmatched' ? (
                    <Tag color="orange">Belum Cocok</Tag>
                ) : (
                    <Tag color="green">Cocok</Tag>
                ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`Rekonsiliasi ${dayjs(reconciliation.period).format('MMMM YYYY')}`} />

            <div style={{ marginBottom: 16 }}>
                <h2 style={{ margin: 0 }}>
                    Rekonsiliasi {reconciliation.account?.code}:{' '}
                    {dayjs(reconciliation.period).format('MMMM YYYY')}
                </h2>
            </div>

            {reconciliation.notes && (
                <Alert
                    type="info"
                    message="Catatan Carry-forward"
                    description={reconciliation.notes}
                    style={{ marginBottom: 16 }}
                    showIcon
                />
            )}

            <Card size="small" style={{ marginBottom: 16, position: 'sticky', top: 0, zIndex: 10 }}>
                <Row gutter={16}>
                    <Col xs={12} sm={6}>
                        <Statistic title="Net Bank" value={balances.bank_net} formatter={(v) => formatIDR(Number(v))} />
                    </Col>
                    <Col xs={12} sm={6}>
                        <Statistic title="Net Buku" value={balances.book_net} formatter={(v) => formatIDR(Number(v))} />
                    </Col>
                    <Col xs={12} sm={6}>
                        <Statistic
                            title="Selisih"
                            value={balances.difference}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{
                                color: Math.abs(Number(balances.difference)) < 0.01 ? token.colorSuccess : token.colorWarning,
                            }}
                        />
                    </Col>
                    <Col xs={12} sm={6}>
                        <Statistic
                            title="Unexplained"
                            value={balances.unexplained}
                            formatter={(v) => formatIDR(Number(v))}
                            valueStyle={{
                                color: Math.abs(Number(balances.unexplained)) < 0.01 ? token.colorSuccess : token.colorError,
                            }}
                        />
                    </Col>
                </Row>
                {selectedBankIds.length > 0 || selectedBookIds.length > 0 ? (
                    <Row gutter={16} style={{ marginTop: 8 }}>
                        <Col span={24}>
                            <Space>
                                <span>
                                    Seleksi: net bank {formatIDR(selectedBankNet)} + net buku{' '}
                                    {formatIDR(selectedBookNet)} = {formatIDR(selectionDifference)}
                                </span>
                                <Button
                                    type="primary"
                                    size="small"
                                    disabled={!canManualMatch}
                                    onClick={manualMatch}
                                >
                                    Cocokkan Manual
                                </Button>
                            </Space>
                        </Col>
                    </Row>
                ) : null}
            </Card>

            <Row gutter={16} style={{ marginBottom: 16 }}>
                <Col xs={24} md={12}>
                    <Card
                        title="Saldo Statement Bank"
                        size="small"
                        extra={
                            editable ? (
                                <Button
                                    size="small"
                                    onClick={() =>
                                        router.post(
                                            route('reconciliations.auto-match', reconciliation.id),
                                        )
                                    }
                                >
                                    Auto-match
                                </Button>
                            ) : null
                        }
                    >
                        <Descriptions size="small" column={1}>
                            <Descriptions.Item label="Saldo Awal">
                                {formatIDR(balances.opening_balance_bank)}
                            </Descriptions.Item>
                            <Descriptions.Item label="Saldo Akhir">
                                {formatIDR(balances.closing_balance_bank)}
                            </Descriptions.Item>
                            <Descriptions.Item label="Adjusted Bank">
                                {formatIDR(balances.adjusted_bank)}
                            </Descriptions.Item>
                        </Descriptions>
                    </Card>
                </Col>
                <Col xs={24} md={12}>
                    <Card title="Saldo Buku (GL)" size="small">
                        <Descriptions size="small" column={1}>
                            <Descriptions.Item label="Saldo Awal">
                                {formatIDR(balances.opening_balance_book)}
                            </Descriptions.Item>
                            <Descriptions.Item label="Saldo Akhir">
                                {formatIDR(balances.closing_balance_book)}
                            </Descriptions.Item>
                            <Descriptions.Item label="Adjusted Book">
                                {formatIDR(balances.adjusted_book)}
                            </Descriptions.Item>
                        </Descriptions>
                    </Card>
                </Col>
            </Row>

            <Row gutter={16}>
                <Col xs={24} lg={12}>
                    <Card title="Baris Bank (Rekening Koran)" size="small">
                        <Table
                            rowKey="id"
                            columns={bankColumns}
                            dataSource={bankLines}
                            pagination={false}
                            size="small"
                            scroll={{ y: 400 }}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Baris Buku (GL)" size="small">
                        <Table
                            rowKey="id"
                            columns={bookColumns}
                            dataSource={bookLines}
                            pagination={false}
                            size="small"
                            scroll={{ y: 400 }}
                        />
                    </Card>
                </Col>
            </Row>

            {matchGroups.length > 0 && (
                <Card title="Grup Cocok" size="small" style={{ marginTop: 16 }}>
                    <Table
                        rowKey="id"
                        size="small"
                        pagination={false}
                        dataSource={matchGroups}
                        columns={[
                            {
                                title: 'Tipe',
                                dataIndex: 'match_type',
                                render: (v: string) =>
                                    v === 'auto' ? 'Otomatis' : 'Manual',
                            },
                            {
                                title: 'Net Bank',
                                dataIndex: 'bank_total',
                                align: 'right' as const,
                                render: (v: number) => formatIDR(v),
                            },
                            {
                                title: 'Net Buku',
                                dataIndex: 'book_total',
                                align: 'right' as const,
                                render: (v: number) => formatIDR(v),
                            },
                            {
                                title: 'Selisih',
                                dataIndex: 'difference',
                                align: 'right' as const,
                                render: (v: number) => formatIDR(v),
                            },
                            {
                                title: '',
                                key: 'action',
                                render: (_: unknown, g: MatchGroup) =>
                                    editable ? (
                                        <Popconfirm
                                            title="Batalkan cocokkan grup ini?"
                                            onConfirm={() =>
                                                router.post(
                                                    route('reconciliations.unmatch', [
                                                        reconciliation.id,
                                                        g.id,
                                                    ]),
                                                )
                                            }
                                        >
                                            <Button size="small">Batal</Button>
                                        </Popconfirm>
                                    ) : null,
                            },
                        ]}
                    />
                </Card>
            )}

            {editable && (
                <Card size="small" style={{ marginTop: 16 }}>
                    <Space>
                        <Popconfirm
                            title="Selesaikan rekonsiliasi? Pastikan semua baris sudah cocok."
                            onConfirm={() =>
                                router.post(route('reconciliations.complete', reconciliation.id))
                            }
                            disabled={!balances.is_balanced}
                        >
                            <Button type="primary" disabled={!balances.is_balanced}>
                                Selesaikan
                            </Button>
                        </Popconfirm>
                        <Popconfirm
                            title="Hapus sesi rekonsiliasi ini?"
                            onConfirm={() =>
                                router.delete(route('reconciliations.destroy', reconciliation.id))
                            }
                        >
                            <Button danger>Hapus Sesi</Button>
                        </Popconfirm>
                        {!balances.is_balanced && (
                            <span style={{ color: token.colorWarning }}>
                                Belum balance: {balances.unmatched_bank_count} bank +{' '}
                                {balances.unmatched_book_count} buku belum cocok
                            </span>
                        )}
                    </Space>
                </Card>
            )}
        </AppLayout>
    );
}

function round2(n: number): number {
    return Math.round(n * 100) / 100;
}
