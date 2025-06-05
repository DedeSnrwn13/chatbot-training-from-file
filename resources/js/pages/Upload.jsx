import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react'; // Import usePage
import { Button, Card, FileInput, Label, Table, Spinner, Select } from 'flowbite-react'; // Import Select
import AppLayout from '@/Layouts/AppLayout';

export default function Upload({ availableLlmProviders }) {
    const [isUploading, setIsUploading] = useState(false);
    const { flash } = usePage().props; // Dapatkan availableLlmProviders dari props

    // Default ke provider pertama jika ada
    const [selectedProvider, setSelectedProvider] = useState(
        availableLlmProviders && availableLlmProviders.length > 0
            ? availableLlmProviders[0].id
            : ''
    );

    const { data, setData, post, progress, reset } = useForm({
        file: null,
        llm_provider: selectedProvider, // Tambahkan llm_provider ke data form
    });

    // Update form data when selectedProvider changes
    useState(() => {
        setData('llm_provider', selectedProvider);
    }, [selectedProvider, setData]); // Gunakan useEffect untuk update state dari prop

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsUploading(true);

        console.log(data);

        await post('/train', {
            preserveScroll: true,
            onSuccess: () => {
                reset('file');
                setIsUploading(false);
                // Tampilkan pesan sukses dari flash (jika ada)
                if (flash?.success) {
                    alert(flash.success);
                }
            },
            onError: (errors) => {
                setIsUploading(false);
                console.error('Upload errors:', errors);
                // Tampilkan pesan error dari flash (jika ada)
                if (flash?.error) {
                    alert(flash.error);
                }
            },
        });
    };

    return (
        <AppLayout>
            <div className="max-w-4xl mx-auto">
                <Card>
                    <h2 className="mb-4 text-2xl font-bold">Upload & Train Knowledge Base</h2>

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

                        {availableLlmProviders && availableLlmProviders.length > 0 && (
                            <div>
                                <div className="block mb-2">
                                    <Label htmlFor="llm_provider_select" value="Pilih Provider AI untuk Training" />
                                </div>
                                <Select
                                    id="llm_provider_select"
                                    value={selectedProvider}
                                    onChange={(e) => {
                                        setSelectedProvider(e.target.value);
                                        setData('llm_provider', e.target.value);
                                    }}
                                    disabled={isUploading}
                                >
                                    {availableLlmProviders.map((provider) => (
                                        <option key={provider.id} value={provider.id}>
                                            {provider.name} (Dimensi: {provider.embedding_dimension})
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        )}

                        {progress && (
                            <div className="w-full bg-gray-200 rounded-full h-2.5">
                                <div
                                    className="bg-blue-600 h-2.5 rounded-full"
                                    style={{ width: `${progress.percentage}%` }} // Akses percentage dari progress
                                />
                            </div>
                        )}

                        <Button type="submit" disabled={!data.file || isUploading || !selectedProvider}>
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
            </div>
        </AppLayout>
    );
}