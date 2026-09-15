import React from 'react';
import { AlertTriangle, RefreshCw, RotateCw } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';

function DefaultFallback({ name, onRetry, onReload }) {
    const isAppLevel = name === 'app';

    return (
        <div
            role="alert"
            className="animate-rise flex min-h-[45vh] w-full items-center justify-center p-6"
        >
            <Card className="w-full max-w-md border-border/60 bg-surface-lowest shadow-ambient-md">
                <CardContent className="flex flex-col items-center gap-4 px-8 py-10 text-center">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-error-container text-error dark:bg-error-container dark:text-error">
                        <AlertTriangle className="size-6" />
                    </span>

                    <div className="grid gap-1.5">
                        <h2 className="font-display text-lg font-bold text-on-surface dark:text-on-surface">
                            {isAppLevel ? 'Something went wrong' : 'This section failed to load'}
                        </h2>
                        <p className="text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {isAppLevel
                                ? 'An unexpected error occurred. Please try again or reload the page.'
                                : 'A section of this page hit an unexpected error. Try again to recover it, or reload the page.'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center justify-center gap-2">
                        <Button onClick={onRetry}>
                            <RefreshCw />
                            Try again
                        </Button>
                        <Button variant="outline" onClick={onReload}>
                            <RotateCw />
                            Reload page
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

/**
 * A reusable error boundary that catches render errors in its subtree and
 * replaces them with a graceful fallback instead of crashing the app.
 *
 * Usage:
 *   <ErrorBoundary name="dashboard">
 *       <DashboardWidget />
 *   </ErrorBoundary>
 *
 * Props:
 *   - name (string): optional label for logging; `'app'` switches the copy
 *     to a whole-page message.
 *   - fallback (node | fn): custom fallback. A function receives
 *     `{ error, name, retry, reload }`.
 *   - onError (fn): `(error, errorInfo, name) => void` override for the
 *     default `console.error` reporting.
 *   - resetKeys (array): when any value changes while an error is shown, the
 *     boundary automatically resets.
 */
export default class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, attempt: 0 };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        const { name = 'app', onError } = this.props;

        if (onError) {
            onError(error, errorInfo, name);
        } else {
            console.error(`[ErrorBoundary:${name}]`, { error, errorInfo });
        }
    }

    componentDidUpdate(prevProps) {
        if (this.state.hasError && this.shouldReset(prevProps)) {
            this.reset();
        }
    }

    shouldReset(prevProps) {
        const { children, resetKeys = [] } = this.props;

        if (children !== prevProps.children) {
            return true;
        }

        return resetKeys.some((key, index) => key !== prevProps.resetKeys?.[index]);
    }

    reset = () => {
        this.setState((state) => ({
            hasError: false,
            error: null,
            attempt: state.attempt + 1,
        }));
    };

    reload = () => {
        window.location.reload();
    };

    render() {
        const { hasError } = this.state;

        if (!hasError) {
            return <React.Fragment key={this.state.attempt}>{this.props.children}</React.Fragment>;
        }

        const { fallback, name = 'app' } = this.props;

        if (fallback) {
            return typeof fallback === 'function'
                ? fallback({
                      error: this.state.error,
                      name,
                      retry: this.reset,
                      reload: this.reload,
                  })
                : fallback;
        }

        return <DefaultFallback name={name} onRetry={this.reset} onReload={this.reload} />;
    }
}