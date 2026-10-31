export default function FieldError({ message }: { message?: string }) {
    return message ? <p className="help is-danger">{message}</p> : null;
}
