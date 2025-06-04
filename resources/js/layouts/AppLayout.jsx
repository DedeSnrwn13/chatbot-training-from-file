import { Link } from '@inertiajs/react';
import { Navbar } from 'flowbite-react';

export default function AppLayout({ children }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <Navbar fluid className="border-b">
                <Navbar.Brand as={Link} href="/">
                    <span className="self-center whitespace-nowrap text-xl font-semibold dark:text-white">
                        AI Chatbot
                    </span>
                </Navbar.Brand>
                <Navbar.Toggle />
                <Navbar.Collapse>
                    <Navbar.Link as={Link} href="/upload" active={window.location.pathname === '/upload'}>
                        Upload & Train
                    </Navbar.Link>
                    <Navbar.Link as={Link} href="/chat" active={window.location.pathname === '/chat'}>
                        Chat
                    </Navbar.Link>
                </Navbar.Collapse>
            </Navbar>

            <main className="container mx-auto px-4 py-8">
                {children}
            </main>
        </div>
    );
}
