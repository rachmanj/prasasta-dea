import AppLayout from '@/Components/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Input, InputNumber, Select } from 'antd';
import dayjs from 'dayjs';

interface Account {
    id: number;
    code: string;
    name: string;
}

interface Props {
    cashAccounts: Account[];
}

const currencyFormatter = (v?: number | string) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) => Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function AssetForm({ cashAccounts }: Props) {
    const defaultCash = cashAccounts.find((a) => a.code === '1000')?.id ?? cashAccounts[0]?.id;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        cost: 0,
        acquisition_date: dayjs().format('YYYY-MM-DD'),
        useful_life_months: 48,
        cash_account_id: defaultCash,
    });

    const submit = () => post(route('assets.store'));

    return (
        <AppLayout>
            <Head title="Tambah Aset" />

            <Card title="Tambah Aset Tetap" style={{ maxWidth: 720 }}>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Nama Aset"
                        required
                        validateStatus={errors.name ? 'error' : undefined}
                        help={errors.name}
                    >
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </Form.Item>

                    <Form.Item
                        label="Harga Perolehan"
                        required
                        validateStatus={errors.cost ? 'error' : undefined}
                        help={errors.cost}
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.cost || null}
                            onChange={(v) => setData('cost', v ?? 0)}
                            formatter={currencyFormatter}
                            parser={currencyParser}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Tanggal Perolehan"
                        required
                        validateStatus={errors.acquisition_date ? 'error' : undefined}
                        help={errors.acquisition_date}
                    >
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.acquisition_date ? dayjs(data.acquisition_date) : null}
                            onChange={(_, ds) => setData('acquisition_date', ds ?? '')}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Masa Manfaat (bulan)"
                        required
                        validateStatus={errors.useful_life_months ? 'error' : undefined}
                        help={errors.useful_life_months}
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={1}
                            value={data.useful_life_months}
                            onChange={(v) => setData('useful_life_months', v ?? 1)}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Akun Kas / Bank"
                        required
                        validateStatus={errors.cash_account_id ? 'error' : undefined}
                        help={errors.cash_account_id}
                    >
                        <Select
                            value={data.cash_account_id}
                            onChange={(v) => setData('cash_account_id', v)}
                            options={cashAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.code} - ${a.name}`,
                            }))}
                        />
                    </Form.Item>

                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Simpan
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
