#include "MetaversoSubsystem.h"

#include "HttpModule.h"
#include "Interfaces/IHttpRequest.h"
#include "Interfaces/IHttpResponse.h"
#include "Serialization/JsonReader.h"
#include "Serialization/JsonSerializer.h"
#include "Dom/JsonObject.h"
#include "Misc/CommandLine.h"
#include "Misc/Parse.h"

void UMetaversoSubsystem::Initialize(FSubsystemCollectionBase& Collection)
{
    Super::Initialize(Collection);
    // Token desde la línea de comandos: -LtiToken=XXXX  (Editor: Play -> Additional Launch Parameters)
    FParse::Value(FCommandLine::Get(), TEXT("LtiToken="), TokenInicial);
}

void UMetaversoSubsystem::IniciarSesion(const FString& LtiSessionToken)
{
    const FString Token = LtiSessionToken.IsEmpty() ? TokenInicial : LtiSessionToken;
    if (Token.IsEmpty())
    {
        OnError.Broadcast(TEXT("No hay token: pásalo a IniciarSesion o usa -LtiToken="));
        return;
    }

    TSharedRef<IHttpRequest, ESPMode::ThreadSafe> Req = FHttpModule::Get().CreateRequest();
    Req->SetURL(BaseUrl + TEXT("/api/game/lti-redeem"));
    Req->SetVerb(TEXT("POST"));
    Req->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
    Req->SetHeader(TEXT("Accept"), TEXT("application/json"));
    Req->SetContentAsString(FString::Printf(TEXT("{\"lti_session_token\":\"%s\"}"), *Token));

    Req->OnProcessRequestComplete().BindLambda(
        [this](FHttpRequestPtr, FHttpResponsePtr Resp, bool bOK)
        {
            const int32 Code = (bOK && Resp.IsValid()) ? Resp->GetResponseCode() : 0;
            if (Code != 200)
            {
                const FString Msg = FString::Printf(TEXT("No se pudo iniciar sesión (HTTP %d)"), Code);
                UE_LOG(LogTemp, Error, TEXT("[Metaverso] %s"), *Msg);
                OnError.Broadcast(Msg);
                return;
            }
            TSharedPtr<FJsonObject> Json;
            const TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(Resp->GetContentAsString());
            if (FJsonSerializer::Deserialize(Reader, Json) && Json.IsValid())
            {
                AccessToken = Json->GetStringField(TEXT("access_token"));
                IdSesion = static_cast<int32>(Json->GetNumberField(TEXT("id_sesion")));
                bSesionLista = true;
                Aciertos = 0;
                UE_LOG(LogTemp, Log, TEXT("[Metaverso] Sesión %d lista."), IdSesion);
                OnSesionLista.Broadcast(FString::Printf(TEXT("Sesión %d lista"), IdSesion));
            }
            else
            {
                OnError.Broadcast(TEXT("Respuesta inválida del servidor (redeem)."));
            }
        });
    Req->ProcessRequest();
}

void UMetaversoSubsystem::SumarAcierto()
{
    ++Aciertos;
    UE_LOG(LogTemp, Log, TEXT("[Metaverso] Aciertos: %d"), Aciertos);
}

void UMetaversoSubsystem::Terminar(int32 TotalActividades)
{
    const int32 Total = FMath::Max(TotalActividades, 1);
    const float Calificacion = FMath::Clamp((static_cast<float>(Aciertos) / Total) * 100.f, 0.f, 100.f);
    UE_LOG(LogTemp, Log, TEXT("[Metaverso] Terminó: %d/%d -> %.1f"), Aciertos, Total, Calificacion);
    EnviarCalificacion(Calificacion);
}

void UMetaversoSubsystem::EnviarCalificacion(float Calificacion)
{
    if (!bSesionLista)
    {
        OnError.Broadcast(TEXT("La sesión no está lista todavía."));
        return;
    }

    TSharedRef<IHttpRequest, ESPMode::ThreadSafe> Req = FHttpModule::Get().CreateRequest();
    Req->SetURL(FString::Printf(TEXT("%s/api/game/sessions/%d/complete"), *BaseUrl, IdSesion));
    Req->SetVerb(TEXT("POST"));
    Req->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
    Req->SetHeader(TEXT("Accept"), TEXT("application/json"));
    Req->SetHeader(TEXT("Authorization"), TEXT("Bearer ") + AccessToken);
    Req->SetContentAsString(FString::Printf(
        TEXT("{\"calificacion\":%.1f,\"datos_resultado\":{\"aciertos\":%d,\"fuente\":\"unreal\"}}"),
        Calificacion, Aciertos));

    Req->OnProcessRequestComplete().BindLambda(
        [this, Calificacion](FHttpRequestPtr, FHttpResponsePtr Resp, bool bOK)
        {
            const int32 Code = (bOK && Resp.IsValid()) ? Resp->GetResponseCode() : 0;
            if (Code == 200)
            {
                UE_LOG(LogTemp, Log, TEXT("[Metaverso] Calificación %.1f enviada a Moodle."), Calificacion);
                OnCalificacionEnviada.Broadcast(FString::Printf(TEXT("Calificación %.0f enviada"), Calificacion));
            }
            else
            {
                const FString Msg = FString::Printf(TEXT("No se pudo enviar la calificación (HTTP %d)"), Code);
                UE_LOG(LogTemp, Error, TEXT("[Metaverso] %s"), *Msg);
                OnError.Broadcast(Msg);
            }
        });
    Req->ProcessRequest();
}
