import AppLayout from '@/Components/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Input, InputNumber, Select } from 'antd';
import dayjs from 'dayjs';

interface Employee {
    id: number;
    name: string;
}

interface Program {
    id: number;
    name: string;
}

interface Props {
    employees: Employee[];
    programs: Program[];
}

const currencyFormatter = (v?: number | string) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;
const currencyParser = (v?: string) => Number((v ?? '').replace(/[^\d]/g, '')) || 0;

export default function CashAdvanceForm({ employees, programs }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        contact_id: undefined as number | undefined,
        program_id: undefined as number | undefined,
        description: '',
        amount: 0,
        date: dayjs().format('YYYY-MM-DD'),
    });

    const submit = () => post(route('cash-advances.store'));

    return (
        <AppLayout>
            <Head title="Tambah Kas Bon" />

            <Card title="Tambah Kas Bon" style={{ maxWidth: 720 }}>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Karyawan"
                        required
                        validateStatus={errors.contact_id ? 'error' : undefined}
                        help={errors.contact_id}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            value={data.contact_id}
                            onChange={(v) => setData('contact_id', v)}
                            options={employees.map((e) => ({ value: e.id, label: e.name }))}
                            placeholder="Pilih karyawan"
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

                    <Form.Item label="Keperluan">
                        <Input
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
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
