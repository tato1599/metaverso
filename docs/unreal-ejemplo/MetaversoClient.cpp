// Ejemplo mínimo: cliente Unreal del Metaverso Escolar TecNM.
// Requiere agregar "HTTP", "Json", "JsonUtilities" en <TuProyecto>.Build.cs
#include "MetaversoClient.h"

#include "HttpModule.h"
#include "Interfaces/IHttpRequest.h"
#include "Interfaces/IHttpResponse.h"
#include "Serialization/JsonReader.h"
#include "Serialization/JsonSerializer.h"
#include "Dom/JsonObject.h"
#include "Misc/CommandLine.h"
#include "Misc/Parse.h"

AMetaversoClient::AMetaversoClient()
{
    PrimaryActorTick.bCanEverTick = false;
}

void AMetaversoClient::BeginPlay()
{
    Super::BeginPlay();

    // Permite pasar el token por línea de comandos:  -LtiToken=XXXX
    FString CmdToken;
    if (FParse::Value(FCommandLine::Get(), TEXT("LtiToken="), CmdToken))
    {
        LtiSessionToken = CmdToken;
    }

    if (!LtiSessionToken.IsEmpty())
    {
        JugarYCompletar();
    }
    else
    {
        UE_LOG(LogTemp, Warning,
            TEXT("[Metaverso] Falta LtiSessionToken: ponlo en el Actor o pasa -LtiToken=XXXX"));
    }
}

void AMetaversoClient::JugarYCompletar()
{
    UE_LOG(LogTemp, Log, TEXT("[Metaverso] Canjeando token en %s ..."), *BaseUrl);
    RedeemToken(LtiSessionToken);
}

void AMetaversoClient::RedeemToken(const FString& Token)
{
    TSharedRef<IHttpRequest, ESPMode::ThreadSafe> Req = FHttpModule::Get().CreateRequest();
    Req->SetURL(BaseUrl + TEXT("/api/game/lti-redeem"));
    Req->SetVerb(TEXT("POST"));
    Req->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
    Req->SetHeader(TEXT("Accept"), TEXT("application/json"));
    Req->SetContentAsString(FString::Printf(TEXT("{\"lti_session_token\":\"%s\"}"), *Token));

    Req->OnProcessRequestComplete().BindLambda(
        [this](FHttpRequestPtr /*Request*/, FHttpResponsePtr Response, bool bOK)
        {
            if (!bOK || !Response.IsValid())
            {
                UE_LOG(LogTemp, Error, TEXT("[Metaverso] redeem: sin respuesta del servidor"));
                return;
            }
            const int32 Code = Response->GetResponseCode();
            const FString Content = Response->GetContentAsString();
            UE_LOG(LogTemp, Log, TEXT("[Metaverso] redeem HTTP %d -> %s"), Code, *Content);
            if (Code != 200)
            {
                UE_LOG(LogTemp, Error, TEXT("[Metaverso] token inválido o expirado (HTTP %d)"), Code);
                return;
            }

            TSharedPtr<FJsonObject> Json;
            const TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(Content);
            if (FJsonSerializer::Deserialize(Reader, Json) && Json.IsValid())
            {
                const FString AccessToken = Json->GetStringField(TEXT("access_token"));
                const int32 IdSesion = static_cast<int32>(Json->GetNumberField(TEXT("id_sesion")));
                UE_LOG(LogTemp, Log, TEXT("[Metaverso] Sesión %d lista. Enviando calificación..."), IdSesion);
                CompleteSession(IdSesion, AccessToken);
            }
            else
            {
                UE_LOG(LogTemp, Error, TEXT("[Metaverso] redeem: JSON inválido"));
            }
        });

    Req->ProcessRequest();
}

void AMetaversoClient::CompleteSession(int32 IdSesion, const FString& AccessToken)
{
    TSharedRef<IHttpRequest, ESPMode::ThreadSafe> Req = FHttpModule::Get().CreateRequest();
    Req->SetURL(FString::Printf(TEXT("%s/api/game/sessions/%d/complete"), *BaseUrl, IdSesion));
    Req->SetVerb(TEXT("POST"));
    Req->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
    Req->SetHeader(TEXT("Accept"), TEXT("application/json"));
    Req->SetHeader(TEXT("Authorization"), TEXT("Bearer ") + AccessToken);
    Req->SetContentAsString(FString::Printf(
        TEXT("{\"calificacion\":%.1f,\"datos_resultado\":{\"aciertos\":9,\"errores\":1,\"fuente\":\"unreal\"}}"),
        Calificacion));

    Req->OnProcessRequestComplete().BindLambda(
        [](FHttpRequestPtr /*Request*/, FHttpResponsePtr Response, bool bOK)
        {
            if (bOK && Response.IsValid())
            {
                UE_LOG(LogTemp, Log, TEXT("[Metaverso] complete HTTP %d -> %s"),
                    Response->GetResponseCode(), *Response->GetContentAsString());
                UE_LOG(LogTemp, Log, TEXT("[Metaverso] Listo. Revisa la calificación en Moodle."));
            }
            else
            {
                UE_LOG(LogTemp, Error, TEXT("[Metaverso] complete: sin respuesta del servidor"));
            }
        });

    Req->ProcessRequest();
}
