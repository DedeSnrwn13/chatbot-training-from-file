import { useState, useRef, useEffect } from 'react';
import { Button, Card, TextInput, Spinner } from 'flowbite-react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Chat() {
    const [messages, setMessages] = useState([]);
    const chatContainerRef = useRef(null);
    const { flash } = usePage().props;

    const { data, setData, post, processing, reset } = useForm({
        prompt: ''
    });

    useEffect(() => {
        if (chatContainerRef.current) {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }, [messages]);

    useEffect(() => {
        console.log('Flash response:', flash);
        if (flash?.response) {
            console.log('Setting message with response:', flash.response);
            setMessages(prev => [...prev, {
                role: 'assistant',
                content: flash.response
            }]);
        }
    }, [flash?.response]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!data.prompt.trim() || processing) return;

        console.log('Sending prompt:', data.prompt);
        // Add user message immediately
        setMessages(prev => [...prev, { role: 'user', content: data.prompt }]);

        post('/chat', {
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('Chat response received:', page);
                reset('prompt');
            },
            onError: (errors) => {
                console.error('Chat errors:', errors);
            }
        });
    };

    return (
        <AppLayout>
            <div className="max-w-4xl mx-auto">
                <Card className="h-[calc(100vh-12rem)]">
                    <div className="flex flex-col h-full">
                        {/* Chat Messages */}
                        <div
                            ref={chatContainerRef}
                            className="flex-1 mb-4 space-y-4 overflow-y-auto"
                        >
                            {messages.map((message, index) => (
                                <div
                                    key={index}
                                    className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                                >
                                    <div
                                        className={`max-w-[80%] rounded-lg px-4 py-2 ${message.role === 'user'
                                            ? 'bg-blue-500 text-white ml-auto'
                                            : 'bg-gray-100 text-gray-900 mr-auto'
                                            }`}
                                    >
                                        {message.content}
                                    </div>
                                </div>
                            ))}
                            {processing && (
                                <div className="flex justify-start">
                                    <div className="px-4 py-2 bg-gray-100 rounded-lg">
                                        <Spinner size="sm" />
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Input Form */}
                        <form onSubmit={handleSubmit} className="flex gap-2">
                            <TextInput
                                type="text"
                                value={data.prompt}
                                onChange={(e) => setData('prompt', e.target.value)}
                                placeholder="Ketik pesan Anda..."
                                className="flex-1"
                                disabled={processing}
                            />
                            <Button type="submit" disabled={processing || !data.prompt.trim()}>
                                Kirim
                            </Button>
                        </form>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
