FROM gcr.io/google.com/cloudsdktool/google-cloud-cli:emulators AS base

ENV PORT=8080
ENV PROJECT_ID="eventsauce"

EXPOSE "$PORT"

ADD docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod 700 /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]


FROM base AS datastore

CMD ["bash","-lc","/google-cloud-sdk/bin/gcloud beta emulators datastore start --host-port=0.0.0.0:${PORT} --project=${PROJECT_ID} --consistency=1.0"]

FROM base AS firestore

CMD ["bash","-lc","/google-cloud-sdk/bin/gcloud beta emulators firestore start --host-port=0.0.0.0:${PORT} --project=${PROJECT_ID}"]