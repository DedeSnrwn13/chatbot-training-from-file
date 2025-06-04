import { useState, useRef, useEffect } from 'react';
import { Button, Card, TextInput, Spinner } from 'flowbite-react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Chat() {
    const [messages, setMessages] = useState([]);
    const chatContainerRef = useRef(null);

    const { data, setData, post, processing, reset } = useForm({
        prompt: ''
    });

    useEffect(() => {
        if (chatContainerRef.current) {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }, [messages]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!data.prompt.trim() || processing) return;

        // Add user message immediately
        setMessages(prev => [...prev, { role: 'user', content: data.prompt }]);

        post('/chat', {
            preserveScroll: true,
            onSuccess: (response) => {
                setMessages(prev => [...prev, {
                    role: 'assistant',
                    content: response.response
                }]);
                reset('prompt');
            },
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
                                    className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'
                                        }`}
                                >
                                    <div
                                        className={`max-w-[80%] rounded-lg px-4 py-2 ${message.role === 'user'
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-gray-100 text-gray-900'
                                            }`}
                                    >
                                        {message.content}
                                    </div>
                                </div>
                            ))}
                            {processing && (
                                <div className="flex justify-center">
                                    <Spinner size="xl" />
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
