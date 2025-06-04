import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button, Card, FileInput, Label, Table, Spinner } from 'flowbite-react';
import AppLayout from '@/Layouts/AppLayout';

export default function Upload() {
    const [isUploading, setIsUploading] = useState(false);
    const { data, setData, post, progress, reset } = useForm({
        file: null,
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsUploading(true);

        await post('/train', {
            preserveScroll: true,
            onSuccess: () => {
                reset('file');
                setIsUploading(false);
            },
            onError: () => setIsUploading(false),
        });
    };

    return (
        <AppLayout>
            <div className="max-w-4xl mx-auto">
                <Card>
                    <h2 className="mb-4 text-2xl font-bold">Upload & Train</h2>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <div className="block mb-2">
                                <Label htmlFor="file" value="Upload File (.txt, .md, .pdf, .html)" />
                            </div>
                            <FileInput
                                id="file"
                                accept=".txt,.md,.pdf,.html"
                                onChange={e => setData('file', e.target.files[0])}
                                disabled={isUploading}
                            />
                        </div>

                        {progress && (
                            <div className="w-full bg-gray-200 rounded-full h-2.5">
                                <div
                                    className="bg-blue-600 h-2.5 rounded-full"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        )}

                        <Button type="submit" disabled={!data.file || isUploading}>
                            {isUploading ? (
                                <>
                                    <Spinner size="sm" className="mr-3" />
                                    <span>Training...</span>
                                </>
                            ) : (
                                'Upload & Train'
                            )}
                        </Button>
                    </form>
                </Card>

                <Card className="mt-8">
                    <h3 className="mb-4 text-xl font-semibold">Uploaded Files</h3>
                    <Table>
                        <Table.Head>
                            <Table.HeadCell>File Name</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell>Upload Date</Table.HeadCell>
                        </Table.Head>
                        <Table.Body className="divide-y">
                            {/* Dummy data - replace with real data from backend */}
                            <Table.Row>
                                <Table.Cell>example.pdf</Table.Cell>
                                <Table.Cell>
                                    <span className="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                        Completed
                                    </span>
                                </Table.Cell>
                                <Table.Cell>2024-03-20 10:00</Table.Cell>
                            </Table.Row>
                        </Table.Body>
                    </Table>
                </Card>
            </div>
        </AppLayout>
    );
}
