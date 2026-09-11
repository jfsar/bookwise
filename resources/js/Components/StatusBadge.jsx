import { Badge } from '@/Components/ui/badge';

const tones = {
    primary: 'bg-primary-container text-primary dark:bg-primary-container dark:text-primary',
    success: 'bg-success-container text-success dark:bg-success-container dark:text-success',
    warning: 'bg-warning-container text-warning dark:bg-warning-container dark:text-warning',
    error: 'bg-error-container text-error dark:bg-error-container dark:text-error',
    info: 'bg-info-container text-info dark:bg-info-container dark:text-info',
    default: 'bg-surface-container text-on-surface-muted dark:bg-surface-container dark:text-on-surface-muted',
};

export default function StatusBadge({ tone = 'default', className, ...props }) {
    return <Badge className={`border-transparent ${tones[tone]}${className ? ` ${className}` : ''}`} {...props} />;
}