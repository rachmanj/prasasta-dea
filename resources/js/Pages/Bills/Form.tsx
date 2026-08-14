import AppLayout from '@/Components/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    DatePicker,
    Form,
    Input,
    InputNumber,
    Select,
} from 'antd';
import dayjs from 'dayjs';

interface Account {
    id: number;
    code: string;
    name: string;
}

interface Contact {
    id: number;
    name: string;
    type: string;
}

interface Props {
    type: string;
    contacts: Contact[];
    offsetAccounts: Account[];
    cashAccounts: Account[];
}

const accountOptions = (list: Account[]) =>
    list.map((a) => ({ value: a.id, label: `${a.code} - ${a.name}` }));

const currencyFormatter = (v?: number | string) =>
    `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) =>
    Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function BillForm({ type, contacts, offsetAccounts }: Props) {
    const isReceivable = type === 'receivable';
    const title = isReceivable ? 'Piutang' : 'Hutang';

    const { data, setData, post, processing, errors } = useForm({
        type,
        contact_id: undefined as number | undefined,
        description: '',
        amount: 0,
        date: dayjs().format('YYYY-MM-DD'),
        due_date: dayjs().add(30, 'day').format('YYYY-MM-DD'),
        account_id: undefined as number | undefined,
    });

    const submit = () => post(route('bills.store'));

    return (
        <AppLayout>
            <Head title={`Tambah ${title}`} />

            <Card title={`Tambah ${title}`} style={{ maxWidth: 720 }}>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label={isReceivable ? 'Pihak yang Berhutang' : 'Pihak yang Ditagih'}
                        required
                        validateStatus={errors.contact_id ? 'error' : undefined}
                        help={errors.contact_id}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            value={data.contact_id}
                            onChange={(v) => setData('contact_id', v)}
                            options={contacts.map((c) => ({ value: c.id, label: c.name }))}
                            placeholder="Pilih kontak"
                        />
                    </Form.Item>

                    <Form.Item label="Tanggal" required validateStatus={errors.date ? 'error' : undefined} help={errors.date}>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.date ? dayjs(data.date) : null}
                            onChange={(_, ds) => setData('date', ds ?? '')}
                        />
                    </Form.Item>

                    <Form.Item label="Jatuh Tempo" required validateStatus={errors.due_date ? 'error' : undefined} help={errors.due_date}>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.due_date ? dayjs(data.due_date) : null}
                            onChange={(_, ds) => setData('due_date', ds ?? '')}
                        />
                    </Form.Item>

                    <Form.Item label="Keterangan">
                        <Input value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </Form.Item>

                    <Form.Item
                        label={isReceivable ? 'Akun Pendapatan' : 'Akun Beban'}
                        required
                        validateStatus={errors.account_id ? 'error' : undefined}
                        help={errors.account_id}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            value={data.account_id}
                            onChange={(v) => setData('account_id', v)}
                            options={accountOptions(offsetAccounts)}
                            placeholder="Pilih akun"
                        />
                    </Form.Item>

                    <Form.Item label="Nominal" required validateStatus={errors.amount ? 'error' : undefined} help={errors.amount}>
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.amount || null}
                            onChange={(v) => setData('amount', v ?? 0)}
                            formatter={currencyFormatter}
                            parser={currencyParser}
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
