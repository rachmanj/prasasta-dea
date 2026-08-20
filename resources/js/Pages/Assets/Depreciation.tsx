import AppLayout from '@/Components/AppLayout';
import { formatIDR } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Space, Table } from 'antd';
import dayjs from 'dayjs';

interface PreviewRow {
    id: number;
    asset_no: string;
    name: string;
    amount: number;
}

interface Props {
    month: string;
    preview: PreviewRow[];
    generated: boolean;
}

export default function AssetDepreciation({ month, preview, generated }: Props) {
    const { data, setData, post, processing } = useForm({
        month,
    });

    const generate = () => {
        router.get(route('assets.depreciation'), { month: data.month });
    };

    const submit = () => post(route('assets.depreciation.post'));

    const total = preview.reduce((sum, row) => sum + Number(row.amount), 0);

    const columns = [
        { title: 'No.', dataIndex: 'asset_no', key: 'asset_no' },
        { title: 'Nama', dataIndex: 'name', key: 'name' },
        {
            title: 'Nominal Penyusutan',
            dataIndex: 'amount',
            key: 'amount',
            align: 'right' as const,
            render: (v: number) => formatIDR(v),
        },
    ];

    return (
        <AppLayout>
            <Head title="Penyusutan Aset" />

            <Card title="Posting Penyusutan" style={{ marginBottom: 16 }}>
                <Form layout="inline" onFinish={generate}>
                    <Form.Item label="Periode" required>
                        <DatePicker
                            picker="month"
                            value={data.month ? dayjs(data.month, 'YYYY-MM') : null}
                            onChange={(_, ds) => setData('month', ds ?? '')}
                            format="MMMM YYYY"
                        />
                    </Form.Item>
                    <Form.Item>
                        <Button type="default" onClick={generate}>
                            Generate
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            {generated && (
                <Card
                    title="Preview Penyusutan"
                    extra={
                        preview.length > 0 ? (
                            <Space>
                                <span>Total: {formatIDR(total)}</span>
                                <Button
                                    type="primary"
                                    loading={processing}
                                    onClick={submit}
                                >
                                    Posting
                                </Button>
                            </Space>
                        ) : null
                    }
                >
                    <Table
                        rowKey="id"
                        columns={columns}
                        dataSource={preview}
                        pagination={false}
                        locale={{ emptyText: 'Tidak ada aset yang perlu disusutkan untuk periode ini.' }}
                    />
                </Card>
            )}
        </AppLayout>
    );
}
