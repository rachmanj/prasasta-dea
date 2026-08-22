import AppLayout from '@/Components/AppLayout';
import { UploadOutlined } from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Select, Upload } from 'antd';
import dayjs from 'dayjs';

interface BankAccount {
    id: number;
    code: string;
    name: string;
}

interface Props {
    bankAccounts: BankAccount[];
    defaultAccountId?: number;
}

export default function ReconciliationsCreate({ bankAccounts, defaultAccountId }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        account_id: defaultAccountId ?? (bankAccounts[0]?.id as number | undefined),
        period: dayjs().startOf('month').format('YYYY-MM-DD'),
        file: null as File | null,
    });

    const submit = () => {
        post(route('reconciliations.store'));
    };

    return (
        <AppLayout>
            <Head title="Mulai Rekonsiliasi" />

            <Card title="Mulai Rekonsiliasi Bank">
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Rekening Bank"
                        validateStatus={errors.account_id ? 'error' : undefined}
                        help={errors.account_id}
                    >
                        <Select
                            value={data.account_id}
                            onChange={(v) => setData('account_id', v)}
                            options={bankAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.code} - ${a.name}`,
                            }))}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Periode (Bulan)"
                        validateStatus={errors.period ? 'error' : undefined}
                        help={errors.period}
                    >
                        <DatePicker
                            picker="month"
                            value={dayjs(data.period)}
                            onChange={(d) =>
                                setData(
                                    'period',
                                    d ? d.startOf('month').format('YYYY-MM-DD') : '',
                                )
                            }
                            format="MMMM YYYY"
                            style={{ width: '100%' }}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Upload Rekening Koran (PDF Mandiri Kopra)"
                        validateStatus={errors.file ? 'error' : undefined}
                        help={errors.file}
                    >
                        <Upload
                            beforeUpload={(file) => {
                                setData('file', file);
                                return false;
                            }}
                            maxCount={1}
                            accept=".pdf"
                            onRemove={() => setData('file', null)}
                        >
                            <Button icon={<UploadOutlined />}>Pilih File PDF</Button>
                        </Upload>
                    </Form.Item>

                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Unggah & Parse
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
