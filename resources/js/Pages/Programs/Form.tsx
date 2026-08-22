import AppLayout from '@/Components/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Input, Select, Space } from 'antd';
import dayjs from 'dayjs';

interface Program {
    id: number;
    name: string;
    type: string;
    start_date: string | null;
    end_date: string | null;
    status: string;
    notes: string | null;
}

interface Props {
    program: Program | null;
}

const TYPE_OPTIONS = [
    { value: 'group', label: 'Kelompok' },
    { value: 'individual', label: 'Individu' },
];

const STATUS_OPTIONS = [
    { value: 'active', label: 'Aktif' },
    { value: 'completed', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

export default function ProgramForm({ program }: Props) {
    const isEdit = !!program;

    const { data, setData, post, put, processing, errors } = useForm({
        name: program?.name ?? '',
        type: program?.type ?? 'group',
        start_date: program?.start_date ?? '',
        end_date: program?.end_date ?? '',
        status: program?.status ?? 'active',
        notes: program?.notes ?? '',
    });

    const submit = () => {
        if (isEdit) {
            put(route('programs.update', program!.id));
        } else {
            post(route('programs.store'));
        }
    };

    return (
        <AppLayout>
            <Head title={isEdit ? 'Edit Program' : 'Program Baru'} />

            <Card
                title={isEdit ? 'Edit Program' : 'Program Baru'}
                style={{ maxWidth: 720 }}
                extra={<Button onClick={() => history.back()}>Kembali</Button>}
            >
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Nama Program"
                        required
                        validateStatus={errors.name ? 'error' : undefined}
                        help={errors.name}
                    >
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Nama program pelatihan"
                        />
                    </Form.Item>

                    <Form.Item
                        label="Jenis"
                        required
                        validateStatus={errors.type ? 'error' : undefined}
                        help={errors.type}
                    >
                        <Select
                            value={data.type}
                            onChange={(v) => setData('type', v)}
                            options={TYPE_OPTIONS}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Tanggal Mulai"
                        validateStatus={errors.start_date ? 'error' : undefined}
                        help={errors.start_date}
                    >
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.start_date ? dayjs(data.start_date) : null}
                            onChange={(_, ds) => setData('start_date', ds ?? '')}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Tanggal Selesai"
                        validateStatus={errors.end_date ? 'error' : undefined}
                        help={errors.end_date}
                    >
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.end_date ? dayjs(data.end_date) : null}
                            onChange={(_, ds) => setData('end_date', ds ?? '')}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Status"
                        required
                        validateStatus={errors.status ? 'error' : undefined}
                        help={errors.status}
                    >
                        <Select
                            value={data.status}
                            onChange={(v) => setData('status', v)}
                            options={STATUS_OPTIONS}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Catatan"
                        validateStatus={errors.notes ? 'error' : undefined}
                        help={errors.notes}
                    >
                        <Input.TextArea
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Catatan tambahan (opsional)"
                        />
                    </Form.Item>

                    <Form.Item>
                        <Space>
                            <Button type="primary" htmlType="submit" loading={processing}>
                                Simpan
                            </Button>
                            <Button onClick={() => history.back()}>Batal</Button>
                        </Space>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
