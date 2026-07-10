FROM golang:1.21-alpine AS builder

WORKDIR /build

COPY go-tracking-core/go.mod go-tracking-core/go.sum ./

RUN go mod download

COPY go-tracking-core/ .

RUN CGO_ENABLED=0 GOOS=linux go build -o tracker-service .

FROM alpine:latest

RUN apk --no-cache add ca-certificates

WORKDIR /root/

COPY --from=builder /build/tracker-service .

EXPOSE 8080

CMD ["./tracker-service"]
